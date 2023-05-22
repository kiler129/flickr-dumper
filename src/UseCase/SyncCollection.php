<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\PhotoCollection;
use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\User;
use App\Entity\Flickr\UserOwnedEntity;
use App\Exception\RuntimeException;
use App\Exception\SyncException;
use App\Filesystem\StorageProvider;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\OwnerAwareIdentity;
use App\Flickr\Url\UrlParser;
use App\Repository\Flickr\PhotoRepository;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoDto;
use App\Struct\PhotoSize;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
final class SyncCollection implements ServiceSubscriberInterface
{
    private const PHOTO_EXTRAS = [
        PhotoExtraFields::DESCRIPTION,
        PhotoExtraFields::DATE_UPLOAD,
        PhotoExtraFields::DATE_TAKEN,
        PhotoExtraFields::LAST_UPDATE,
        PhotoExtraFields::VIEWS,
        PhotoExtraFields::MEDIA,
        PhotoExtraFields::PATH_ALIAS, //screenname; for e.g. faves we should also grab owner name to allow easier lookup

        //We're only requesting sensibly-sized pictures, not thumbnails
        PhotoExtraFields::URL_MEDIUM_640,
        PhotoExtraFields::URL_MEDIUM_800,
        PhotoExtraFields::URL_LARGE_1024,
        PhotoExtraFields::URL_LARGE_1600,
        PhotoExtraFields::URL_LARGE_2048,
        PhotoExtraFields::URL_XLARGE_3K,
        PhotoExtraFields::URL_XLARGE_4K,
        PhotoExtraFields::URL_XLARGE_4K_2to1,
        PhotoExtraFields::URL_XLARGE_5K,
        PhotoExtraFields::URL_XLARGE_6K,
        PhotoExtraFields::URL_ORIGINAL,
    ];

    private ApiClientConfigFactory $clientCfgFactory;

    /**
     * By default, as the name "sync" implies, everything is synced. Sometimes quickly ignoring complete collections
     * may be desired (e.g. processing a long list and the server crashed).
     */
    public bool $syncCompleted = true;

    /**
     * Existing collection and items (not for now but in the future) are only updated when their timestamp is newer than
     * what we know about them. In certain cases (e.g. damaged collection) it may be beneficial to ignore timestamp and
     * re-list collection anyway and verify items as a list.
     */
    public bool $trustUpdateTimestamps = true;


    /**
     * By default, we trust database Photo record. When this option is disabled files will be re-queued to the sink even
     * if their index suggests they should be fine. This option can also be used when the sync was previously ran with
     * noop-sink.
     */
    public bool $trustPhotoRecords = true;

    /**
     * When set it will attempt to reset/regenerate identity of both the Flickr API client (new key, new UA, new proxy)
     * and the download client (new UA, new proxy). Naturally, if there's no or single proxy defined this option has
     * no effect on proxy; likewise the same applies to API keys. However, UA is always changed.
     * When exactly identities are swapped is deliberately opaque as it's determined to look real-ish.
     */
    public bool $switchIdentities = false;

    public function __construct(
        private ContainerInterface $locator,
        private FlickrApiClient $api,
        private LoggerInterface $log,
        private UserRepository $userRepo,
        private PhotoRepository $photoRepo,
        private ResolveOwner $resolveOwner,
        private UrlParser $urlParser,
        private EntityManagerInterface $om
    )
    {
    }

    /**
     * @param TSyncCallback $sink
     */
    public function syncCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        if (!($identity instanceof AlbumIdentity)) {
            dump($identity);
            throw new RuntimeException('Not implemented yet: sync of ' . $identity::class);
        }

        $ret = $this->syncAlbum($identity, $sink);

        $this->om->flush();
        return $ret;
    }

    /**
     * @param TSyncCallback $sink
     */
    public function syncAlbum(AlbumIdentity $identity, callable $sink): bool
    {
        $repo = $this->locator->get(PhotosetRepository::class);
        $collection = $repo->find($identity->setId);
        $localLastUpdated = null;

        if ($collection !== null) { //collection exists, but we don't know in what state - check it first
            //This is early-return to prevent even metadata from being fetched
            if ($collection->isSyncCompleted() && !$this->syncCompleted) {
                $this->log->debug(
                    '{id} sync skipped: collection completed at least one full sync and the setting explicitly ' .
                    'disabled syncing completed collections',
                    ['id' => $collection->getUserReadableId()]
                );
                return true;
            }

            $statusCheck = $this->verifyCollectionState($collection); //this checks some static properties of collection

            if ($statusCheck !== null) {
                return $statusCheck;
            }

            //this date will be updated so we're saving it to compare with the newest one
            $localLastUpdated = $collection->getDateLastUpdated() ?? $collection->getDateCreated();
        }

        // ***** ACTUALLY IMPORTANT *****
        // DO NOT assume $identity->owner is NSID - it may be, but it can be a screenname. You CANNOT blindly try using
        // it as NSID. You MUST resolve it via API or database. See Flickr\Url\UrlParser for detailed explanation why.
        $this->ensureOwnerNSID($identity, $collection);

        $apiCollection = $this->api->getPhotosets()->getInfo($identity->ownerNSID, $identity->setId);
        $apiInfo = $apiCollection->getContent();
        if ($collection === null) { //it's a good time to create collection to consistent work on it
            \assert((string)(int)$apiInfo['id'] === $apiInfo['id'], 'Expected (int)id but got "' . $apiInfo['id'] ."");
            $collection = new Photoset((int)$apiInfo['id'], $this->getOwnerUser($identity->ownerNSID));
        }

        //Some properties are UPDATED regardless of whether album contents is updated
        $this->setPhotosetMetadata($collection, $apiInfo);
        $repo->save($collection, true);

        if (!$this->shouldSyncCollectionItems($collection, $localLastUpdated)) {
            return true; //not syncing but not due to an error
        }

        if (!$this->syncCollectionPhotos($collection, $this->getAlbumPhotos($identity), $sink)) {
            $this->log->debug(
                '{id} sync failed: see previous messages for details',
                ['id' => $collection->getUserReadableId()]
            );
            return false;
        }

        $collection->setDateSyncCompleted(new \DateTimeImmutable());
        $repo->save($collection);

        return true;

        //todo: albums contain count_photos_* fields (all, public, friend etc) -> maybe there should be a warning if
        // "total" isn't the same as the count? (i.e. we see less photos via API than count so probably lack of oauth)
    }

    private function shouldSyncCollectionItems(PhotoCollection $collection, ?\DateTimeInterface $localLastUpdated): bool
    {
        if (!$collection->isSyncCompleted()) {
            $this->log->info(
                '{id} will sync: local copy was never fully synced',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

        if (!$this->trustUpdateTimestamps) {
            $this->log->info(
                '{id} will forcefully sync: timestamps are ignored by setting',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

        $newLastUpdated = $collection->getDateLastUpdated();
        if ($newLastUpdated === null || ($localLastUpdated !== null && $newLastUpdated <= $localLastUpdated)) {
            $this->log->debug(
                '{id} sync skipped: collection was synced at least once before and it was never updated',
                ['id' => $collection->getUserReadableId()]
            );
            return false;
        }

        $this->log->info('{id} will sync: local copy outdated ({ldate}) vs. remote ({rdate})',
                         [
                             'id' => $collection->getUserReadableId(),
                             'ldate' => $localLastUpdated ?? 'never updated',
                             'rdate' => $newLastUpdated,
                         ]
        );

        return true;
    }

    /**
     * @param iterable<array> $photos
     * @param TSyncCallback $sink
     */
    private function syncCollectionPhotos(PhotoCollection $collection, iterable $photos, callable $sink): bool
    {
        $localPhotos = $collection->getPhotos();
        $this->log->info('{col} photos syncing started', ['col' => $collection->getUserReadableId()]);

        $result = true;
        foreach ($photos as $apiPhoto) {
            $remotePhoto = PhotoDto::fromApiResponse($apiPhoto);
            $photoId = $remotePhoto->id;
            $remoteSize = $remotePhoto->getLargestSize();

            if ($remoteSize === null) {
                $this->log->warning(
                    'Photo id={phid} returned no available sizes from API - skipping', ['phid' => $photoId]
                );
                continue;
            }

            $localPhoto = $this->photoRepo->find($photoId);
            $isNewLocalPhoto = false; //we need this variable as after metadata update new and old photos look the same
            if ($localPhoto === null) { //nothing matched => new photo
                $this->log->info(
                    '{col} new photo id={phid} size={size}',
                    ['col' => $collection->getUserReadableId(), 'phid' => $photoId, 'size' => $remoteSize->name]
                );

                //create a SCAFFOLDING of a photo only
                $owner = $this->resolveOwner->resolveOwnerUser($remotePhoto, $collection);
                $localPhoto = new Photo(
                    $photoId, $owner, $remoteSize, $remotePhoto->getSizeUrl($remoteSize)
                );
                $isNewLocalPhoto = true;
            } elseif (!$this->verifyPhotoState($localPhoto)) { //the state of new photos is known so only check old
                continue; //Skipped by state failure of to-be updated photo
            }

            //Similar to collections, regardless if the photo has been updated on the remote we want to sync metadata
            // as some of them don't trigger photo being updated (e.g. number of views)
            $localLastUpdated = $localPhoto->getDateLastUpdated(); //the metadata update may overwrite this for existing
            $this->setPhotoMetadata($localPhoto, $remotePhoto);
            if (!$localPhotos->contains($localPhoto)) {
                $this->log->info(
                    '{colid}: linking photo id={phid}',
                    ['colid' => $collection->getUserReadableId(), 'phid' => $photoId]
                );
                $localPhotos->add($localPhoto);
            }
            $this->photoRepo->save($localPhoto, true);

            if ($this->shouldSyncPhoto($photoId, $isNewLocalPhoto, $localPhoto, $localLastUpdated, $remotePhoto, $remoteSize)) {
                $result = $sink($localPhoto) || $result;
            }
        }

        return $result;
    }

    private function shouldSyncPhoto(int $photoId, bool $isNewLocalPhoto, Photo $localPhoto, ?\DateTimeInterface $localLastUpdated, PhotoDto $remotePhoto, PhotoSize $remoteSize): bool
    {
        if ($isNewLocalPhoto) {
            $this->log->info('Syncing photo id={phid}: new photo not yet synced', ['phid' => $photoId]);
            return true;
        }

        if (!$this->trustPhotoRecords) {
            $this->log->info(
                'Syncing photo id={phid}: setting enforces re-verification of photo records', ['phid' => $photoId]
            );
            return true;
        }

        //We need to check variants before update timestamps as bigger photo can appear without update date changing
        // as owner may simply change profile settings to allow higher sizes for ALL photos on the account
        $localSize = $localPhoto->getFileVersion();
        switch ($localSize->compareWith($remoteSize)) {
            case -1:
                $this->log->info(
                    'Syncing photo id={phid}: remote size ({rsize}) is larger than local ({lsize})',
                    ['phid' => $photoId, 'rsize' => $remoteSize->name, 'lsize' => $localSize->name]
                );
                return true;

            case 0:
                $this->log->info(
                    'Skip sync of photo id={phid}: remote size and local sizes the same ({size})',
                    ['phid' => $photoId, 'size' => $remoteSize->name]
                );
                return false;

            case 1: //technically photo may be REPLACED with a different one but having higher resolution is better
                $this->log->warning(
                    'Skip sync of photo id={phid}: remote size ({rsize}) is SMALLER than local ({lsize})',
                    ['phid' => $photoId, 'rsize' => $remoteSize->name, 'lsize' => $localSize->name]
                );
                return false;
        }

        //Usually this means a user replaced a photo. If timestamps aren't trusted the only way to know is to
        // look at the filename (not whole URL as they may change their infra!)
        $localCdnName = $this->urlParser->getStaticFilename($localPhoto->getCdnUrl());
        $remoteCdnName = $this->urlParser->getStaticFilename($remotePhoto->getSizeUrl($remoteSize));
        if (!$this->trustUpdateTimestamps && $localCdnName !== $remoteCdnName) {
            $this->log->info(
                'Syncing photo id={phid}: remote CDN file ({rfile}) is different than local ({lfile})',
                ['phid' => $photoId, 'rfile' => $remoteCdnName, 'lfile' => $localCdnName]
            );

            return true;
        }

        if ($localLastUpdated !== null && isset($remotePhoto->dateUpdated) &&
            $remotePhoto->dateUpdated > $localLastUpdated) {
            $this->log->info(
                'Syncing photo id={phid}: remote record is newer ({rdate}) than local ({ldate})',
                ['phid' => $photoId, 'rdate' => $remotePhoto->dateUpdated, 'ldate' => $localLastUpdated]
            );

            return true;
        }

        $this->log->info(
            'Skip sync of photo id={phid}: remote and local records appear the same and file repair was not requested',
            ['phid' => $photoId]
        );

        return false;
    }

    private function setPhotosetMetadata(Photoset $local, array $apiPhotoset): void
    {
        $local->setApiData($apiPhotoset)
            ->setDateLastRetrieved(new \DateTimeImmutable());

        if (isset($apiPhotoset['title']['_content'])) {
            $local->setTitle($apiPhotoset['title']['_content']);
        }

        if (isset($apiPhotoset['description']['_content'])) {
            $local->setDescription($apiPhotoset['description']['_content']);
        }

        if ($local->getDateCreated() === null) {
            $local->setDateCreated((new \DateTimeImmutable())->setTimestamp((int)$apiPhotoset['date_create']));
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        $dateUpdateApi = (new \DateTimeImmutable())->setTimestamp((int)$apiPhotoset['date_update']);
        if ($dateUpdateLocal === null || $dateUpdateApi > $dateUpdateLocal) {
            $local->setDateLastUpdated($dateUpdateApi);
        }

        if ($local->getOwner() === null) {
            $local->setOwner($this->getOwnerUser($apiPhotoset['owner']));
        }
    }

    private function setPhotoMetadata(Photo $local, PhotoDto $apiPhoto): void
    {
        $local->setApiData($apiPhoto->apiData)
              ->setDateLastRetrieved(new \DateTimeImmutable());

        if (isset($apiPhoto->title)) {
            $local->setTitle($apiPhoto->title);
        }

        if (isset($apiPhoto->description)) {
            $local->setDescription($apiPhoto->description);
        }

        if ($local->getDateTaken() === null && isset($apiPhoto->dateTaken)) {
            $local->setDateTaken($apiPhoto->dateTaken);
        }

        if ($local->getDateUploaded() === null && isset($apiPhoto->dateUploaded)) {
            $local->setDateUploaded($apiPhoto->dateUploaded);
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        if (isset($apiPhoto->dateUpdated) && ($dateUpdateLocal === null || $apiPhoto->dateUpdated > $dateUpdateLocal)) {
            $local->setDateLastUpdated($apiPhoto->dateUpdated);
        }

        if (isset($apiPhoto->views)) {
            $local->setViews($apiPhoto->views);
        }

        //We're not updating version here are presumably the caller has more knowledge about sizes etc
    }

    private function getIdentitySwitchCallback(): ?callable
    {
        if (!$this->switchIdentities) {
            return null;
        }

        return function (int $page): void {
            $this->log->debug('Switching API identity during sync after page ' . $page);
            $this->clientCfgFactory ??= $this->locator->get(ApiClientConfigFactory::class);
            $cfg = $this->clientCfgFactory->getWithRandomClient();
            $this->api = $this->api->withConfiguration($cfg);
        };
    }

    /**
     * @return iterable<PhotoDto>
     */
    private function getAlbumPhotos(AlbumIdentity $albumIdentity): iterable
    {
        return $this->api->getPhotosets()
                         ->getPhotosIterable(
                             $albumIdentity->ownerNSID,
                             $albumIdentity->setId,
                             PhotosetsEndpoint::MAX_PER_PAGE,
                             self::PHOTO_EXTRAS,
                             pageFinishCallback: $this->getIdentitySwitchCallback()
                         );
    }

    private function verifyCollectionState(PhotoCollection $collection): ?bool
    {
        if ($collection->isBlacklisted()) {
            $this->log->warning(
                '{id} sync skipped: collection explicitly blacklisted',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

        if (!$this->syncCompleted && $collection->isSyncCompleted()) {
            $this->log->info(
                '{id} sync skipped: it exists and it was completed before. The sync of completed collections has ' .
                'been explicitly disabled by configuration.',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

        if ($collection->isDeleted()) {
            $this->log->info(
                '{id} sync skipped: collection previously deleted',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

        if ($collection->isWriteLocked()) {
            $this->log->warning(
                '{id} sync cannot complete: collection is write-locked',
                ['id' => $collection->getUserReadableId()]
            );
            return false;
        }

        return null;
    }

    private function verifyPhotoState(Photo $photo): bool
    {
        if ($photo->isBlacklisted()) {
            $this->log->warning(
                'Photo {id} sync skipped: photo explicitly blacklisted',
                ['id' => $photo->getId()]
            );
            return true;
        }

        if ($photo->isDeleted()) {
            $this->log->info(
                'Photo {id} sync skipped: photo previously deleted',
                ['id' => $photo->getId()]
            );
            return true;
        }

        if ($photo->isWriteLocked()) {
            $this->log->warning(
                'Photo {id} sync cannot complete: collection is write-locked',
                ['id' => $photo->getId()]
            );
            return false;
        }

        if (!$photo->isFilesystemInSync()) {
            $this->log->warning('Photo {id} metadata is not in sync with filesystem', ['id' => $photo->getId()]);
        }

        return true;
    }

    /**
     * Ensures that collection identifier that is owner aware has a complete owner identity (i.e. NSID)
     */
    private function ensureOwnerNSID(OwnerAwareIdentity $identity, ?UserOwnedEntity $entity): void
    {
        if ($identity->hasNSID()) {
            return;
        }

        $entityOwner = $entity?->getOwner()?->getNsid();
        if ($entityOwner !== null) {
            $identity->setNSID($entityOwner);
            return;
        }

        $userIdentity = $this->resolveOwner->lookupUserByPathAlias($identity->getOwner());
        if ($userIdentity !== null) {
            $identity->setNSID($userIdentity->nsid);
            return;
        }

        throw new SyncException(\sprintf('Unable to determine NSID of owner "%s"', $identity->getOwner()));
    }

    private function getOwnerUser(string $nsid): User
    {
        $user = $this->userRepo->find($nsid);
        if ($user === null) {
            $identity = $this->resolveOwner->lookupUserByPathAlias($nsid);
            $user = new User($identity->nsid, $identity->userName, $identity->screenName);
            $this->userRepo->save($user, true);
        }

        return $user;
    }

    public static function getSubscribedServices(): array
    {
        return [
            ApiClientConfigFactory::class, //used only when randomization desired
            PhotosetRepository::class,
        ];
    }
}
