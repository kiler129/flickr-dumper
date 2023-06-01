<?php
declare(strict_types=1);

namespace App\Factory;

use App\Exception\RuntimeException;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\GalleryIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\UserFavesIdentity;
use App\UseCase\FetchPhotoToDisk;
use App\UseCase\Sync\SyncCollectionStrategy;
use App\UseCase\Sync\SyncGalleryStrategy;
use App\UseCase\Sync\SyncPhotosetStrategy;
use App\UseCase\Sync\SyncUserFavoritesStrategy;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 *
 * @todo this can probably be replaced with some magic locator from container to not map this manually
 */
final class SyncStrategyFactory implements ServiceSubscriberInterface
{
    public function __construct(private ContainerInterface $locator) {
    }
    /**
     * @param TSyncCallback $sink
     */
    public function createForCollection(MediaCollectionIdentity $identity): SyncCollectionStrategy
    {
        if (!$this->locator->has($identity::class)) {
            dump($identity);
            throw new RuntimeException('Not implemented yet: sync of ' . $identity::class);
        }

        return $this->locator->get($identity::class);
    }

    public static function getSubscribedServices(): array
    {
        return [
            GalleryIdentity::class => SyncGalleryStrategy::class,
            AlbumIdentity::class => SyncPhotosetStrategy::class,
            UserFavesIdentity::class => SyncUserFavoritesStrategy::class,
        ];
    }
}
