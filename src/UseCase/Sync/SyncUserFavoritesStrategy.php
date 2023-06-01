<?php
declare(strict_types=1);

namespace App\UseCase\Sync;

use App\Entity\Flickr\UserFavorites;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\Enum\PhotoExtraFields;
use App\Flickr\Struct\ApiDto\PhotoDto;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\UserFavesIdentity;
use App\Repository\Flickr\UserFavoritesRepository;
use App\UseCase\FetchPhotoToDisk;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
final class SyncUserFavoritesStrategy extends SyncCollectionStrategy implements ServiceSubscriberInterface
{

    public function __construct(
        ContainerInterface $locator,
        private UserFavoritesRepository $repo
    ) {
        parent::__construct($locator);
    }

    /**
     * @param TSyncCallback $sink
     * @param UserFavesIdentity $identity
     */
    protected function syncSpecificCollection(MediaCollectionIdentity $identity, callable $sink): bool
    {
        \assert($identity instanceof UserFavesIdentity);

        // ***** ACTUALLY IMPORTANT *****
        // DO NOT assume $identity->owner is NSID - it may be, but it can be a screenname. You CANNOT blindly try using
        // it as NSID. You MUST resolve it via API or database. See Flickr\Url\UrlParser for detailed explanation why.
        $this->ensureOwnerNSID($identity, null);

        $collection = $this->repo->find($identity->ownerNSID);
        if ($collection !== null) { //collection exists, but we don't know in what state - check it first
            $statusCheck = $this->verifyCollectionState($collection); //this checks some static properties of collection
            if ($statusCheck !== null) {
                return $statusCheck;
            }
        } else {
            $collection = new UserFavorites($this->getOwnerUser($identity->ownerNSID));
            $this->repo->save($collection, true);
        }

        if (!$this->shouldSyncCollectionItems($collection, null)) { //favorites have no update date on the API ;<
            return true; //not syncing but not due to an error
        }

        $photos = $this->getFavoritePhotos($identity);
        $collection->setDateLastRetrieved(new \DateTimeImmutable());
        if (!$this->syncCollectionPhotos($collection, $photos, $sink)) {
            $this->log->error(
                '{id} sync failed: see previous messages for details',
                ['id' => $collection->getUserReadableId()]
            );

            $collection->setDateSyncCompleted(null);
            return false;
        }

        $collection->setDateSyncCompleted(new \DateTimeImmutable());
        $this->repo->save($collection, true);

        return true;
    }

    /**
     * @return iterable<PhotoDto>
     */
    private function getFavoritePhotos(UserFavesIdentity $favesIdentity): iterable
    {
        $fields = static::PHOTO_EXTRAS;
        $fields[] = PhotoExtraFields::MAGIC_INTERSTITIAL; //in faves this allows for restricted photos w/o signature

        return $this->api->getFavorites()
                         ->getListIterable(
                             $favesIdentity->ownerNSID,
                             null,
                             null,
                             $fields,
                             PhotosetsEndpoint::MAX_PER_PAGE,
                             pageFinishCallback: $this->getIdentitySwitchCallback()
                         );
    }

    static protected function supportsIdentity(MediaCollectionIdentity $identity): bool
    {
        return $identity instanceof UserFavesIdentity;
    }
}
