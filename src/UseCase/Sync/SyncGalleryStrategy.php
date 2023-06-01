<?php
declare(strict_types=1);

namespace App\UseCase\Sync;

use App\Entity\Flickr\Collection\Gallery;
use App\Flickr\ClientEndpoint\GalleriesEndpoint;
use App\Flickr\Struct\ApiDto\GalleryDto;
use App\Flickr\Struct\ApiDto\PhotoDto;
use App\Flickr\Struct\Identity\GalleryIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Repository\Flickr\GalleryRepository;
use App\UseCase\FetchPhotoToDisk;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
final class SyncGalleryStrategy extends SyncCollectionStrategy implements ServiceSubscriberInterface
{
    public function __construct(
        ContainerInterface $locator,
        private GalleryRepository $repo
    ) {
        parent::__construct($locator);
    }

    /**
     * @param TSyncCallback $sink
     * @param GalleryIdentity $identity
     */
    protected function syncSpecificCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        \assert($identity instanceof GalleryIdentity);

        $collection = $this->repo->find($identity->setId);
        $localLastUpdated = null;

        if ($collection !== null) { //collection exists, but we don't know in what state - check it first
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

        $apiCollection = $this->api->getGalleries()->getInfo($identity->ownerNSID, $identity->setId);
        if (!$apiCollection->isSuccessful()) {
            $this->log->error('Gallery {id} sync failed: API failure (see above)', ['id' => $identity->setId]);
            return false;
        }
        $apiGallery = GalleryDto::fromGenericApiResponse($apiCollection->getContent());

        if ($collection === null) { //it's a good time to create collection to consistent work on it
            $collection = new Gallery($apiGallery->id, $this->getOwnerUser($identity->ownerNSID));
        }

        //Some properties are UPDATED regardless of whether gallery contents is updated
        $this->setGalleryMetadata($collection, $apiGallery);
        $this->repo->save($collection, true);

        if (!$this->shouldSyncCollectionItems($collection, $localLastUpdated)) {
            return true; //not syncing but not due to an error
        }

        if (!$this->syncCollectionPhotos($collection, $this->getGalleryPhotos($identity), $sink)) {
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

        //todo: galleries contain count_photos_* fields -> maybe there should be a warning if
        // "total" isn't the same as the count? (i.e. we see less photos via API than count so probably lack of oauth)
    }

    /**
     * @return iterable<PhotoDto>
     */
    private function getGalleryPhotos(GalleryIdentity $galleryIdentity): iterable
    {
        if ($this->switchIdentities) {
            $this->ensureIdentity();
        }

        return $this->api->getGalleries()
                         ->getPhotosIterable(
                             $galleryIdentity->ownerNSID,
                             $galleryIdentity->setId,
                             GalleriesEndpoint::MAX_PER_PAGE,
                             static::PHOTO_EXTRAS,
                         );
    }

    /**
     * @todo This should be moved to GalleryDtoEntityTransformer for consistency
     */
    private function setGalleryMetadata(
        Gallery $local,
        GalleryDto $apiGallery,
        ?\DateTimeInterface $lastRetrieved = null
    ): void {
        $local->setApiData($apiGallery->apiData)
              ->setDateLastRetrieved($lastRetrieved ?? new \DateTimeImmutable());

        if ($apiGallery->hasProperty('title')) {
            $local->setTitle($apiGallery->title);
        }

        if ($apiGallery->hasProperty('description')) {
            $local->setDescription($apiGallery->description);
        }

        if ($local->getDateCreated() === null && isset($apiGallery->dateCreated)) {
            $local->setDateCreated($apiGallery->dateCreated);
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        if (isset($apiGallery->dateUpdated) && ($dateUpdateLocal === null || $apiGallery->dateUpdated > $dateUpdateLocal)) {
            $local->setDateLastUpdated($apiGallery->dateUpdated);
        }

        if (isset($apiGallery->views)) {
            $local->remoteStats->views = $apiGallery->views;
        }

        if (isset($apiGallery->commentsCount)) {
            $local->remoteStats->comments = $apiGallery->commentsCount;
        }

        if (isset($apiGallery->photosCount)) {
            $local->remoteStats->photos = $apiGallery->photosCount;
        }

        if (isset($apiGallery->videosCount)) {
            $local->remoteStats->videos = $apiGallery->videosCount;
        }

        if ($local->getOwner() === null) {
            $local->setOwner($this->getOwnerUser($apiGallery->ownerNsid));
        }
    }

    static protected function supportsIdentity(MediaCollectionIdentity $identity): bool
    {
        return $identity instanceof GalleryIdentity;
    }
}
