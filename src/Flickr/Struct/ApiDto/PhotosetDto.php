<?php
declare(strict_types=1);

namespace App\Flickr\Struct\ApiDto;

/**
 * Simple DTO to map array response from API to a typed object
 *
 * @property-read int $id Identifier of a photoset/album
 * @property-read string $secret Secret used for most CDN sizes
 * @property-read string $server Server id where photos are
 * @property-read int $farm Server group id
 * @property-read int $primaryPhotoId
 * @property-read string $title
 * @property-read string $description
 * @property-read \DateTimeInterface $dateCreated
 * @property-read \DateTimeInterface $dateUpdated
 * @property-read string $ownerUsername Username (e.g. "Space X Photos"); available: photosets*,
 * @property-read string $ownerNsid NSID of the owner (e.g. "1234@N01")
 * @property-read int $views Number of views for the album
 * @property-read int $commentsCount Number of comments for the album
 * @property-read int $photosCount Number of photos in the album
 * @property-read int $videosCount Number of videos in the album
 */
final class PhotosetDto extends BaseDto
{
    protected const SIMPLE_TYPECAST_MAP = [
        'id' => 'int',
        'secret' => 'string',
        'server' => 'string',
        'farm' => 'int',
        'primary' => 'int',
        'username' => 'string',
        'owner' => 'string',
        'count_views' => 'int',
        'count_comments' => 'int',
        'count_photos' => 'int',
        'count_videos' => 'int',
    ];
    
    protected const KNOWN_TO_API = [
        'primaryPhotoId' => 'primary',
        'dateCreated' => 'date_create',
        'dateUpdated' => 'date_update',
        'ownerUsername' => 'username',
        'ownerNsid' => 'owner',
        'views' => 'count_views',
        'commentsCount' => 'count_comments',
        'photosCount' => 'count_photos',
        'videosCount' => 'count_videos',
    ];

    protected function transformValue(string $apiName, mixed $value): mixed
    {
        return match ($apiName) {
            //"title" doesn't have "_content" USUALLY but SOMETIMES it does lol
            'title' => $value['_content'] ?? $value,
            'description' => $value['_content'] ?? $value,
            'date_create', 'date_update' => $this->castDateTime($value),
            default => $this->transformAutocast($apiName, $value)
        };
    }
}
