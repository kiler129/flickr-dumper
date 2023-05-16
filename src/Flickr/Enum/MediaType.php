<?php
declare(strict_types=1);

namespace App\Flickr\Enum;

enum MediaType: string
{
    case ALL = 'all';
    case PHOTOS = 'photos';
    case VIDEOS = 'videos';
}
