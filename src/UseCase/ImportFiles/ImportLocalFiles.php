<?php
declare(strict_types=1);

namespace App\UseCase\ImportFiles;

use App\Entity\Flickr\Photo;
use App\Filesystem\StorageProvider;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\ClientEndpoint\PhotosEndpoint;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Flickr\Struct\PhotoDto;
use App\Flickr\Struct\PhotoVariant;
use App\Flickr\Url\UrlGenerator;
use App\Repository\Flickr\PhotoRepository;
use App\Transformer\PhotoDtoEntityTransformer;
use App\UseCase\ResolveOwner;
use DateTimeImmutable;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Throwable;
use function fclose;
use function file_exists;
use function fopen;

class ImportLocalFiles implements ServiceSubscriberInterface
{
    /**
     * Whether to try to recover metadata from JSON files that may have been left by other API clients
     */
    public bool $attemptMetadataRecovery = true;

    /**
     * Files will be copied to central storage and organized. If you want to link them instead from the original folder
     * as-is you can set it to false.
     */
    public bool $copyFilesToStorage = true;

    /**
     * When set it will attempt to reset/regenerate identity of both the Flickr API client (new key, new UA, new proxy)
     * and the download client (new UA, new proxy). Naturally, if there's no or single proxy defined this option has
     * no effect on proxy; likewise the same applies to API keys. However, UA is always changed.
     * When exactly identities are swapped is deliberately opaque as it's determined to look real-ish.
     */
    public bool $switchIdentities = false;

    private PhotosEndpoint $photosApi;
    private LoadExternalPhotoMetadata $recoverMetadata;

    public function __construct(
        private ContainerInterface $locator,
        private LoggerInterface $log,
        private PhotoRepository $photoRepo,
        private UrlGenerator $urlGen,
        private StorageProvider $storage,
        private ResolveOwner $resolveOwner,
        private PhotoDtoEntityTransformer $photoTransformer,
        private FlickrApiClient $flickrApi,
    ) {
        $this->photosApi = $this->flickrApi->getPhotos();
    }

    /**
     * Attempts to import a single file into the database
     */
    public function importFile(SplFileInfo $file): ?Photo
    {
        $realPath = $file->getRealPath();
        $path = $file->getRelativePath();
        $this->log->debug('Importing file {file} from {dir}', ['dir' => $path, 'file' => $file->getFilename()]);

        try {
            $variant = PhotoVariant::createFromFilename($file->getFilename(), true);
        } catch (Throwable $e) {
            $this->log->warning(
                'Cannot import {file} - cannot parse into photo variant (not a photo?)',
                ['file' => $file->getRealPath(), 'exception' => $e]
            );

            return null;
        }

        $photo = $this->tryUpdateExistingLocalPhoto($file, $variant)
                 ?? $this->createNewLocalPhoto($file, $variant);

        $this->log->notice('File {pth} processed to Photo id={phid}', ['phid' => $photo->getId(), 'pth' => $realPath]);

        return $photo;
    }

    /**
     * Attempts to update an existing file based on file being imported
     *
     * This method will update the local indexed copy file if it's larger. The metadata will NOT be updated from the
     * imported files, as the assumption is if the file is already index the metadata we have is better than what is
     * offered by the bare file import.
     */
    private function tryUpdateExistingLocalPhoto(SplFileInfo $file, PhotoVariant $variant): ?Photo {
        $localPhoto = $this->photoRepo->find($variant->photoId);
        if ($localPhoto === null) {
            $this->log->debug('No local photo record for id={phid}', ['phid' => $variant->photoId]);
            return null;
        }

        //We always get remote metadata if it's "cheap" (i.e. from disk) to have more real CDN url
        $remotePhoto = $this->attemptMetadataRecovery ? $this->recoverMetadata($file, $variant) : null;

        $localPath = $localPhoto->getLocalPath();
        if (file_exists($localPath) && $variant->size->compareWith($localPhoto->getFileVersion()) < 1) {
            $this->log->info(
                'Photo id={phid} exists locally with its file that is same or larger than to-be imported - skipping',
                ['phid' => $variant->photoId]
            );
            return $localPhoto;
        }

        $this->log->info(
            'Photo id={phid} exists locally but its file is damaged or smaller than imported - updating file only',
            ['phid' => $variant->photoId]
        );
        $localPhoto->lockForWrite();
        $localPhoto->setFileVersion($variant->size);
        $path = $this->getUpdatePhotoFile($file, $localPhoto);
        $localPhoto
            ->setCdnUrl(
                $remotePhoto?->getSizeUrl($variant->size) ??
                $this->urlGen->getCdnLink($variant, $remotePhoto->originalFormat ?? 'jpg')
            )
            ->setLocalPath($path)
            ->unlockForWrite(true);
        $this->photoRepo->save($localPhoto, true);

        return $localPhoto;
    }

    /**
     * Creates a brand-new Photo record based on the file
     */
    private function createNewLocalPhoto(SplFileInfo $file, PhotoVariant $variant): Photo
    {
        $remotePhoto = $this->attemptMetadataRecovery ? $this->recoverMetadata($file, $variant) : null;
        if ($remotePhoto === null) {
            $this->log->debug(
                'No photo metadata archive locally for photo id={phid} - getting from API',
                ['phid' => $variant->photoId]
            );

            if ($this->switchIdentities && \mt_rand(0, 10) === 0) {
                $this->switchApiIdentity();
            }
            $api = $this->photosApi->getInfo($variant->photoId);
            $remotePhoto = PhotoDto::fromExtendedApiResponse($api->getContent());
        }

        $this->log->debug('Creating new photo record for id={phid}', ['phid' => $variant->photoId]);
        $owner = $this->resolveOwner->resolveOwnerUser($remotePhoto, null);
        $localPhoto = new Photo(
            $variant->photoId,
            $owner,
            $variant->size,
            $remotePhoto->hasSizeUrl($variant->size)
                ? $remotePhoto->getSizeUrl($variant->size)
                : $this->urlGen->getCdnLink($variant, $remotePhoto->originalFormat ?? 'jpg'),
        );

        $localPhoto->lockForWrite();
        $fileModTime = $file->getMTime();
        $this->photoTransformer->setPhotoMetadata(
            $localPhoto,
            $remotePhoto,
            $fileModTime === false ? null : (new DateTimeImmutable())->setTimestamp($fileModTime)
        );
        $path = $this->getUpdatePhotoFile($file, $localPhoto, $this->copyFilesToStorage);
        $localPhoto
            ->setLocalPath($path)
            ->setFileVersion($variant->size)
            ->unlockForWrite(true);
        $this->photoRepo->save($localPhoto, true);

        return $localPhoto;
    }

    /**
     * Computes a canonical path for a photo. If needed it will also copy the file to a proper storage location.
     */
    private function getUpdatePhotoFile(SplFileInfo $fileFound, Photo $localPhoto): string
    {
        $path = $fileFound->getRealPath();

        if (!$this->copyFilesToStorage) {
            $this->log->debug(
                'File copying is disabled - using path {path} for photo id={phid} as-is',
                ['phid' => $localPhoto->getId(), 'path' => $path]
            );

            return $path;
        }

        $this->log->info(
            'Copying photo id={phid} file {path} to local datastore',
            ['phid' => $localPhoto->getId(), 'path' => $path]
        );
        $fit = $this->storage->newForPhoto($localPhoto);

        $file = fopen($path, 'rb');
        if ($file === false) {
            $this->log->error(
                'Failed to open photo id={phid} file {path}',
                ['phid' => $localPhoto->getId(), 'path' => $path]
            );
        }
        $bytesCopied = $fit->writeFromStream($file);
        fclose($file);
        $this->storage->finish($fit);
        $this->log->debug(
            'Copy photo id={phid} finished after {size} bytes',
            ['phid' => $localPhoto->getId(), 'size' => $bytesCopied]
        );

        return $fit->savePath;
    }

    /**
     * Attempts to find metadata for an image file that may have been preserved before
     */
    private function recoverMetadata(SplFileInfo $file, PhotoVariant $variant): ?PhotoDto
    {
        $path = $file->getPath();
        $this->recoverMetadata ??= $this->locator->get(LoadExternalPhotoMetadata::class);

        foreach ([
                     $variant->photoId . '.json',
                     $variant->photoId . '_' . $variant->secret . '.json',
                     $variant->photoId . '.json',
                     $file->getBasename() . '.json',
                 ] as $json
        ) {
            $dto = $this->recoverMetadata->tryLoadMetadataFromFile($path . '/' . $json, $variant->photoId);

            if ($dto !== null) {
                return $dto;
            }
        }

        $this->log->warning('Could not find archived metadata for photo id={phid}', ['phid' => $variant->photoId]);
        return null;

        //In the future we could e.g. use Symfony Finder to fuzzy-look for files in a whole directory that have photo id
        // and secret in them and are JSONs
    }

    private function switchApiIdentity(): void
    {
        $this->clientCfgFactory ??= $this->locator->get(ApiClientConfigFactory::class);
        $cfg = $this->clientCfgFactory->getWithRandomClient();
        $this->photosApi = $this->flickrApi->withConfiguration($cfg)->getPhotos();
    }

    public static function getSubscribedServices(): array
    {
        return [
            LoadExternalPhotoMetadata::class,
            ApiClientConfigFactory::class
        ];
    }
}
