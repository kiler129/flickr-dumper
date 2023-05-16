<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\PhotoCollection;
use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\User;
use App\Entity\Flickr\UserOwnedEntity;
use App\Exception\RuntimeException;
use App\Exception\SyncException;
use App\Filesystem\StorageProvider;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Flickr\Struct\ApiResponse;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\CollectionIdentity;
use App\Flickr\Struct\Identity\OwnerAwareIdentity;
use App\Flickr\Struct\Identity\UserIdentity;
use App\Repository\Flickr\PhotosetRepository;
use App\Repository\Flickr\UserRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class SyncCollection implements ServiceSubscriberInterface
{
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
     * By default, we trust database photo record. When this option is disabled files will be verified for existence.
     * This is useful if someone deleted some files manually.
     * Keep in mind photos deleted properly will NOT be deleted (as their shadows still exist in the database).
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
        private StorageProvider $storage,
        private FlickrApiClient $api,
        private LoggerInterface $log,
        private UserRepository $userRepo,
    )
    {
    }

    public function syncCollection(CollectionIdentity $identity): bool
    {
        if (!($identity instanceof AlbumIdentity)) {
            throw new RuntimeException('Not implemented yet');
        }

        return $this->syncAlbum($identity);
    }

    public function syncAlbum(AlbumIdentity $identity): bool
    {
        $repo = $this->locator->get(PhotosetRepository::class);
        $collection = $repo->find($identity->setId);
        $localLastUpdated = null;

        if ($collection !== null) { //collection exists, but we don't know in what state - check it first
            $statusCheck = $this->verifyCollectionState($collection); //this checks some static properties of collection

            if ($statusCheck !== null) {
                return $statusCheck;
            }

            $localLastUpdated = $collection->getDateLastUpdated() ?? $collection->getDateCreated();
        } //we're not creating the collection in "else" as it may still not exits etc.

        // ***** ACTUALLY IMPORTANT *****
        // DO NOT assume $identity->owner is NSID - it may be, but it can be a screenname. You CANNOT blindly try using
        // it as NSID. You MUST resolve it via API or database. See Flickr\Url\UrlParser for detailed explanation why.
        $this->ensureOwnerNSID($identity, $collection);

        $apiCollection = $this->api->getPhotosets()->getInfo($identity->ownerNSID, $identity->setId);
        if ($collection !== null) { //Some properties are UPDATED regardless of whether album contents is updated


        }


        dd($apiCollection->getContent());

        echo 'OK, done here';

    }

    private function setPhotosetMetadata(Photoset $local, array $apiInfo): void
    {
        $local->setApiData($apiInfo)
              ->setTitle($apiInfo['title']['_content'] ?? $local->getTitle())
              ->setDescription($apiInfo['description']['_content'] ?? $local->getDescription())
              ->setDateLastRetrieved(new \DateTimeImmutable())
        ;

        if ($local->getDateCreated() === null) {
            $local->setDateCreated((new \DateTimeImmutable())->setTimestamp($apiInfo['date_create']));
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        $dateUpdateApi = (new \DateTimeImmutable())->setTimestamp($apiInfo['date_update']);
        if ($dateUpdateLocal === null || $dateUpdateApi > $dateUpdateLocal) {
            $local->setDateLastUpdated($dateUpdateApi);
        }

        if ($local->getOwner() === null) {
            $local->setOwner($this->getOwnerUser($apiInfo['owner']));
        }

        $this->locator->get(PhotosetRepository::class)->save($local);
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
            $this->log->info(
                '{id} sync cannot complete: collection is write-locked',
                ['id' => $collection->getUserReadableId()]
            );
            return false;
        }

        return null;
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

        $userIdentity = $this->locator->get(TransformUserIdentifier::class)
                                      ->lookupUserByIdentifier($identity->getOwner());
        if ($userIdentity !== null) {
            $identity->setNSID($userIdentity->nsid);
            return;
        }

        throw new SyncException(\sprintf('Unable to determine NSID of owner "%s"', $identity->getOwner()));
    }

    private function getOwnerUser(string $nsid): User
    {
        $user = $this->userRepo->find($nsid);
        if ($user !== null) {
            return $user;
        }

        //TODO: it would be good if this saved more data but I don't see and **EASY** way to get it quickly here
        $user = new User($nsid, '');
        $this->userRepo->save($user);

        return $user;
    }

    public static function getSubscribedServices(): array
    {
        return [
            ApiClientConfigFactory::class, //used only when randomization desired
            TransformUserIdentifier::class,
            PhotosetRepository::class,
        ];
    }
}
