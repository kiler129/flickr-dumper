<?php
declare(strict_types=1);

namespace App\Flickr\Url;

final class UrlGenerator
{
    public function getPhotoViewLinkById(string $photoId): string
    {
        //This doesn't seem to be documented anywhere, but Flickr employees referenced this on community forums
        return 'https://www.flickr.com/photo.gne?id=' . $photoId;
    }

    public function getProfileLink(string $screenNameOrNsid): string
    {
        return 'https://www.flickr.com/people/' . $screenNameOrNsid;
    }
}
