<?php
declare(strict_types=1);

namespace App\UseCase\Sync;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\PhotoCollection;
use App\Entity\Flickr\User;
use App\Entity\Flickr\UserOwnedEntity;
use App\Exception\InvalidArgumentException;
use App\Exception\SyncException;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\OwnerAwareIdentity;
use App\Flickr\Struct\PhotoDto;
use App\Flickr\Url\UrlParser;
use App\Repository\Flickr\PhotoRepository;
use App\Repository\Flickr\UserRepository;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoSize;
use App\Transformer\PhotoDtoEntityTransformer;
use App\UseCase\ResolveOwner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class SyncCollectionStrategy
{
    protected const PHOTO_EXTRAS = [
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
        protected FlickrApiClient $api,
        protected LoggerInterface $log,
        private UserRepository $userRepo,
        private PhotoRepository $photoRepo,
        private PhotoDtoEntityTransformer $photoTransformer,
        private ResolveOwner $resolveOwner,
        private UrlParser $urlParser,
        private EntityManagerInterface $om,
    ) {
    }

    abstract static protected function supportsIdentity(MediaCollectionIdentity $identity): bool;

    abstract protected function syncSpecificCollection(MediaCollectionIdentity $identity, callable $sink): bool;

    final public function syncCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        if (!static::supportsIdentity($identity)) {
            throw new InvalidArgumentException(
                \sprintf('%s does not support %s - did you pick wrong strategy?', $this::class, $identity::class)
            );
        }

        $result = $this->syncSpecificCollection($identity, $sink);
        $this->om->flush();

        return $result;
    }

    protected function getOwnerUser(string $nsid): User
    {
        $user = $this->userRepo->find($nsid);
        if ($user === null) {
            $identity = $this->resolveOwner->lookupUserByPathAlias($nsid);
            $user = new User($identity->nsid, $identity->userName, $identity->screenName);
            $this->userRepo->save($user, true);
        }

        return $user;
    }

    /**
     * Ensures that collection identifier that is owner aware has a complete owner identity (i.e. NSID)
     */
    protected function ensureOwnerNSID(OwnerAwareIdentity $identity, ?UserOwnedEntity $entity): void
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

    protected function verifyCollectionState(PhotoCollection $collection): ?bool
    {
        //This is early-return to prevent even metadata from being fetched
        if ($collection->isSyncCompleted() && !$this->syncCompleted) {
            $this->log->debug(
                '{id} sync skipped: collection completed at least one full sync and the setting explicitly ' .
                'disabled syncing completed collections',
                ['id' => $collection->getUserReadableId()]
            );
            return true;
        }

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

    protected function shouldSyncCollectionItems(PhotoCollection $collection, ?\DateTimeInterface $localLastUpdated): bool
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
    protected function syncCollectionPhotos(PhotoCollection $collection, iterable $photos, callable $sink): bool
    {
        $localPhotos = $collection->getPhotos();
        $this->log->info('{col} photos syncing started', ['col' => $collection->getUserReadableId()]);

        $result = true;
        foreach ($photos as $apiPhoto) {
            $remotePhoto = PhotoDto::fromGenericApiResponse($apiPhoto);
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
            $this->photoTransformer->setPhotoMetadata($localPhoto, $remotePhoto);
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

    protected function getIdentitySwitchCallback(): ?callable
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

    public static function getSubscribedServices(): array
    {
        return [
            ApiClientConfigFactory::class, //used only when randomization desired
        ];
    }

}
