<?php
declare(strict_types=1);

namespace App\UseCase\Sync;

use App\Entity\Flickr\Photoset;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\PhotoDto;
use App\Flickr\Url\UrlParser;
use App\Repository\Flickr\PhotoRepository;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use App\Transformer\PhotoDtoEntityTransformer;
use App\UseCase\FetchPhotoToDisk;
use App\UseCase\ResolveOwner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
final class SyncPhotosetStrategy extends SyncCollectionStrategy implements ServiceSubscriberInterface
{
    public function __construct(
        ContainerInterface $locator,
        FlickrApiClient $api,
        LoggerInterface $log,
        UserRepository $userRepo,
        PhotoRepository $photoRepo,
        PhotoDtoEntityTransformer $photoTransformer,
        ResolveOwner $resolveOwner,
        UrlParser $urlParser,
        EntityManagerInterface $om,
        private PhotosetRepository $repo
    ) {
        parent::__construct(
            $locator,
            $api,
            $log,
            $userRepo,
            $photoRepo,
            $photoTransformer,
            $resolveOwner,
            $urlParser,
            $om
        );
    }

    /**
     * @param TSyncCallback $sink
     * @param AlbumIdentity $identity
     */
    protected function syncSpecificCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        $collection = $this->repo->find($identity->setId);
        $localLastUpdated = null;

        if ($collection !== null) { //collection exists, but we don't know in what state - check it first
            ////This is early-return to prevent even metadata from being fetched
            //if ($collection->isSyncCompleted() && !$this->syncCompleted) {
            //    $this->log->debug(
            //        '{id} sync skipped: collection completed at least one full sync and the setting explicitly ' .
            //        'disabled syncing completed collections',
            //        ['id' => $collection->getUserReadableId()]
            //    );
            //    return true;
            //}

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
        $this->repo->save($collection, true);

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
        $this->repo->save($collection);

        return true;

        //todo: albums contain count_photos_* fields (all, public, friend etc) -> maybe there should be a warning if
        // "total" isn't the same as the count? (i.e. we see less photos via API than count so probably lack of oauth)
    }

    /**
     * @return iterable<PhotoDto>
     */
    private function getAlbumPhotos(AlbumIdentity $albumIdentity): iterable
    {
        if ($this->switchIdentities) {
            $this->ensureIdentity();
        }

        return $this->api->getPhotosets()
                         ->getPhotosIterable(
                             $albumIdentity->ownerNSID,
                             $albumIdentity->setId,
                             PhotosetsEndpoint::MAX_PER_PAGE,
                             self::PHOTO_EXTRAS,
                         );
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

    static protected function supportsIdentity(MediaCollectionIdentity $identity): bool
    {
        return $identity instanceof AlbumIdentity;
    }
}
