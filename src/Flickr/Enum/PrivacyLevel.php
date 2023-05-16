<?php
declare(strict_types=1);

namespace App\Flickr\Enum;

/**
 * See "privacy_filter" on https://www.flickr.com/services/api/flickr.photosets.getPhotos.html
 */
enum PrivacyLevel: int
{
    case PUBLIC = 1;
    case PRIVATE_FRIENDS = 2;
    case PRIVATE_FAMILY = 3;
    case PRIVATE_FRIENDS_FAMILY = 4;
    case PRIVATE = 5;
}
