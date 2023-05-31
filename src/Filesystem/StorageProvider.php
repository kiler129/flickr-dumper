<?php
declare(strict_types=1);

namespace App\Filesystem;

use App\Entity\Flickr\Photo;
use App\Flickr\Struct\FileInTransit;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class StorageProvider
{
    public function __construct(private string $storageRoot, private LoggerInterface $log, private Filesystem $fs)
    {
    }

    /**
     * @param Photo $photo
     *
     * @return resource
     */
    public function newForPhoto(Photo $photo): FileInTransit
    {
        $size = \strtolower($photo->getFileVersion()->name);
        $tmpExt = 'inprg' . \date('YmdHis');
        $path = $this->getDirectoryForPhoto($photo) . '/' . $photo->getId() . '-' . $size . '.';

        return FileInTransit::open(tmpPath: $path . $tmpExt, savePath: $path . 'jpg');
    }

    public function photoExists(Photo $photo): bool
    {
        $path = $photo->getLocalPath();

        return $path === null ? false : $this->fs->exists($path);
    }

    public function finish(FileInTransit $file): void
    {
        \assert($file->savePath); //files created here have it - we generally expect it at this point
        $file->close();

        $this->fs->rename($file->tmpPath, $file->savePath, true);
    }

    public function abort(FileInTransit $file): void
    {
        \assert($file->savePath); //files created here have it - we generally expect it at this point
        $file->close();

        $this->fs->remove($file->tmpPath);
    }

    private function getDirectoryForPhoto(Photo $photo): string
    {
        $date = $photo->getDateTaken() ?? $photo->getDateUploaded() ?? $photo->getDateLastRetrieved();

        $ownerPathNSID =  \str_replace('@', '_', $photo->getOwner()->getNsid());
        $path = $this->storageRoot . '/files/' . $ownerPathNSID[0] . $ownerPathNSID[1] . '/' . $ownerPathNSID . '/' .
                $date->format('Y/m');
        $this->fs->mkdir($path);

        return $path;
    }
}
