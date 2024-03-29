<?php
declare(strict_types=1);

namespace App\Struct;

/** @deprecated This should be changed to enum and in App\Flickr NS */
final class MediaType
{
    public const ALL_TYPES = 'all';
    public const PHOTOS    = 'photos';
    public const VIDEOS    = 'videos';

    public const ALL = [
        self::ALL_TYPES,
        self::PHOTOS,
        self::VIDEOS,
    ];
}
