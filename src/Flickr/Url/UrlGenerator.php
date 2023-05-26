<?php
declare(strict_types=1);

namespace App\Flickr\Url;

use App\Flickr\Struct\PhotoVariant;

final class UrlGenerator
{
    private const BASE = 'https://www.flickr.com';

    public function getPhotoViewLinkById(int $photoId): string
    {
        //This doesn't seem to be documented anywhere, but Flickr employees referenced this on community forums
        return \sprintf('%s/photo.gne?id=%d', self::BASE, $photoId);
    }

    public function getProfileLink(string $screenNameOrNsid): string
    {
        return \sprintf('%s/people/%s', self::BASE, $screenNameOrNsid);
    }

    /**
     * @param PhotoVariant $variant
     * @param string       $fileExtension Seems to be ignored largely if anything remotely pleausble (e.g. jpeg)
     * @param int          $server Seems to be ignored (legacy?)
     *
     * @return string
     */
    public function getCdnLink(PhotoVariant $variant, string $fileExtension = 'jpg', int $server = 65535): string
    {
        return \sprintf('https://live.staticflickr.com/%d/%s.%s', $server, $variant->asBasename(), $fileExtension);
    }
}
