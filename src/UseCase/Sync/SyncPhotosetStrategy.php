<?php
declare(strict_types=1);

namespace App\UseCase\Sync;

use App\Entity\Flickr\Collection\Photoset;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\Struct\ApiDto\PhotoDto;
use App\Flickr\Struct\ApiDto\PhotosetDto;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Repository\Flickr\PhotosetRepository;
use App\UseCase\FetchPhotoToDisk;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
final class SyncPhotosetStrategy extends SyncCollectionStrategy implements ServiceSubscriberInterface
{
    public function __construct(
        ContainerInterface $locator,
        private PhotosetRepository $repo
    ) {
        parent::__construct($locator);
    }

    /**
     * @param TSyncCallback $sink
     * @param AlbumIdentity $identity
     */
    protected function syncSpecificCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        \assert($identity instanceof AlbumIdentity);

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
        if (!$apiCollection->isSuccessful()) {
            $this->log->error('Album/photoset {id} sync failed: API failure (see above)', ['id' => $identity->setId]);
            return false;
        }
        $apiPhotoset = PhotosetDto::fromGenericApiResponse($apiCollection->getContent());

        if ($collection === null) { //it's a good time to create collection to consistent work on it
            $collection = new Photoset($apiPhotoset->id, $this->getOwnerUser($identity->ownerNSID));
        }

        //Some properties are UPDATED regardless of whether album contents is updated
        $this->setPhotosetMetadata($collection, $apiPhotoset);
        $this->repo->save($collection, true);

        if (!$this->shouldSyncCollectionItems($collection, $localLastUpdated)) {
            return true; //not syncing but not due to an error
        }

        if (!$this->syncCollectionPhotos($collection, $this->getAlbumPhotos($identity), $sink)) {
            $this->log->error(
                '{id} sync failed: see previous messages for details',
                ['id' => $collection->getUserReadableId()]
            );

            $collection->setDateSyncCompleted(null);
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
                             static::PHOTO_EXTRAS,
                         );
    }

    /**
     * @todo This should be moved to PhotosetDtoEntityTransformer for consistency
     */
    private function setPhotosetMetadata(
        Photoset $local,
        PhotosetDto $apiPhotoset,
        ?\DateTimeInterface $lastRetrieved = null
    ): void
    {
        $local->setApiData($apiPhotoset->apiData)
              ->setDateLastRetrieved($lastRetrieved ?? new \DateTimeImmutable());

        if ($apiPhotoset->hasProperty('title')) {
            $local->setTitle($apiPhotoset->title);
        }

        if ($apiPhotoset->hasProperty('description')) {
            $local->setDescription($apiPhotoset->description);
        }

        if ($local->getDateCreated() === null && isset($apiPhotoset->dateCreated)) {
            $local->setDateCreated($apiPhotoset->dateCreated);
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        if (isset($apiPhotoset->dateUpdated) && ($dateUpdateLocal === null || $apiPhotoset->dateUpdated > $dateUpdateLocal)) {
            $local->setDateLastUpdated($apiPhotoset->dateUpdated);
        }

        if (isset($apiPhotoset->views)) {
            $local->remoteStats->views = $apiPhotoset->views;
        }

        if (isset($apiPhotoset->commentsCount)) {
            $local->remoteStats->comments = $apiPhotoset->commentsCount;
        }

        if (isset($apiPhotoset->photosCount)) {
            $local->remoteStats->photos = $apiPhotoset->photosCount;
        }

        if (isset($apiPhotoset->videosCount)) {
            $local->remoteStats->videos = $apiPhotoset->videosCount;
        }

        if ($local->getOwner() === null) {
            $local->setOwner($this->getOwnerUser($apiPhotoset->ownerNsid));
        }
    }

    static protected function supportsIdentity(MediaCollectionIdentity $identity): bool
    {
        return $identity instanceof AlbumIdentity;
    }
}
