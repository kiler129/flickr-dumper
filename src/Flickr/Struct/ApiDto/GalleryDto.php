<?php
declare(strict_types=1);

namespace App\Flickr\Struct\ApiDto;

/**
 * Simple DTO to map array response from API to a typed object
 *
 * @property-read int $id Identifier of the gallery (e.g. 72157721792219398)
 * @property-read string $internalId Unknown addtl. id (e.g. "66911286-72157721792219398")
 * @property-read string $url Url of the gallery view page
 * @property-read string $ownerUsername Username (e.g. "Space X Photos"); available: photosets*,
 * @property-read string $ownerNsid NSID of the owner (e.g. "1234@N01")
 * @property-read string $iconServer Part of buddyicons (https://www.flickr.com/services/api/misc.buddyicons.html)
 * @property-read int $iconFarm Part of buddyicons (https://www.flickr.com/services/api/misc.buddyicons.html)
 * @property-read int $primaryPhotoId
 * @property-read \DateTimeInterface $dateCreated
 * @property-read \DateTimeInterface $dateUpdated
 * @property-read int $photosCount Number of photos in the gallery
 * @property-read int $videosCount Number of videos in the gallery
 * @property-read int $totalCount Number of all media items in the gallery
 * @property-read int $views Number of views for the gallery
 * @property-read int $commentsCount Number of comments for the gallery
 *
 * There are more fields that aren't mapped as they don't seem useful: sort_group, cover_photos, current_state,
 *   primary_photo_server, primary_photo_farm, primary_photo_secret
 */
final class GalleryDto extends BaseDto
{
    protected const SIMPLE_TYPECAST_MAP = [
        'id' => 'string',
        'gallery_id' => 'int',
        'url' => 'string',
        'username' => 'string',
        'owner' => 'string',
        'iconserver' => 'string',
        'iconfarm' => 'int',
        'primary_photo_id' => 'int',
        'count_photos' => 'int',
        'count_videos' => 'int',
        'count_total' => 'int',
        'count_views' => 'int',
        'count_comments' => 'int',
    ];
    
    protected const KNOWN_TO_API = [
        'id' => 'gallery_id',
        'internalId' => 'id',
        'ownerUsername' => 'username',
        'ownerNsid' => 'owner',
        'iconServer' => 'iconserver',
        'iconFarm' => 'iconfarm',
        'primaryPhotoId' => 'primary_photo_id',
        'dateCreated' => 'date_create',
        'dateUpdated' => 'date_update',
        'photosCount' => 'count_photos',
        'videosCount' => 'count_videos',
        'totalCount' => 'count_total',
        'views' => 'count_views',
        'commentsCount' => 'count_comments',
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
