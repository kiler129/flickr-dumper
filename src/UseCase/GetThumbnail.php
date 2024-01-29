<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\Photo;
use Spatie\Image\Enums\FlipDirection;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Enums\Orientation;
use Spatie\Image\Image;

class GetThumbnail
{
    public function getThumbnailForPhoto(Photo $photo, bool $forceRegenerate = false): string
    {
        $thumbPath = $this->getThumbPath($photo);
        if ($forceRegenerate || !\file_exists($thumbPath)) {
            $image = $this->load($photo);
            $this->resize($image);
            $this->applyAutorotate($photo, $image);
            $this->dumpToFile($image, $thumbPath);
        }

        return $thumbPath;
    }

    private function getThumbPath(Photo $photo): string
    {
        return $photo->getLocalPath() . '.thumb';
    }

    private function load(Photo $photo): Image
    {
        return Image::useImageDriver(ImageDriver::Gd)
                    ->loadFile($photo->getLocalPath());
    }

    private function resize(Image $image, int $maxWidth = 1024, int $maxHeight = 1024): void
    {
        $image
            ->width($maxWidth)
            ->height($maxHeight);
    }

    private function applyAutorotate(Photo $photo, Image $image): void
    {
        //Image has broken EXIF....
        //https://github.com/spatie/image/blob/2aaa76cacb928b5463ce8f9827190ca8d9a905cb/src/Drivers/Gd/GdDriver.php#L528
        //In addition their 90 & 270° are swapped.... true Laravel-libraries quality
        if (!\function_exists('exif_read_data')) {
            return;
        }

        $exif = @\exif_read_data($photo->getLocalPath());
        if ($exif === false) {
            return; //broken or non-existent exif
        }

        switch ($exif['Orientation'] ?? 1) { //1 is normal
            case 2:
                $image->flip(FlipDirection::Horizontal);
                break;
            case 3:
                $image->orientation(Orientation::Rotate180);
                break;
            case 4:
                $image->flip(FlipDirection::Vertical);
                break;
            case 5:
                $image->flip(FlipDirection::Vertical);
                //intentionally no break
            case 6:
                $image->orientation(Orientation::Rotate90);
                break;
            case 7:
                $image->flip(FlipDirection::Vertical);
                //intentionally no break
            case 8:
                $image->orientation(Orientation::Rotate270);
                break;
        }
    }

    private function dumpToFile(Image $image, string $destPath): void
    {
        $image->quality(70)
              ->format('jpg')
              ->save($destPath);
    }
}
