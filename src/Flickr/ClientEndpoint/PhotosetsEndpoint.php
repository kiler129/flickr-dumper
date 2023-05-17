<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Enum\MediaType;
use App\Flickr\Enum\PrivacyLevel;
use App\Flickr\Struct\ApiResponse;
use App\Struct\PhotoDto;

/**
 * Photosets aka albums
 *
 * Note: some of the Flickr docs are incorrect (e.g. regarding pagination being optional)
 */
class PhotosetsEndpoint
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.photosets.getList.html
     */
    public const MAX_PER_PAGE = 500;

    public function __construct(private FlickrApiClient $client)
    {
    }

    public function getList(
        string $userId,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $primaryPhotoExtras = [],
        array $photoIds = [],
        array $sortGroups = [],
    ): ApiResponse {
        $params = [
            'user_id' => $userId,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);

        if (\count($primaryPhotoExtras) !== 0) {
            $params['primary_photo_extras'] = $this->serializeExtras($primaryPhotoExtras);
        }

        if (\count($photoIds) !== 0) {
            $params['photo_ids'] = \implode(',', $photoIds);
        }
        if (\count($sortGroups) !== 0) {
            $params['sort_groups'] = \implode(',', $sortGroups);
        }

        return $this->client->call('flickr.photosets.getList', $params, 'photosets');
    }

    public function getListIterable(
        string $userId,
        int $perPage = self::MAX_PER_PAGE,
        array $primaryPhotoExtras = [],
        array $photoIds = [],
        array $sortGroups = []
    ): iterable {
        return $this->flattenPages(
            fn(int $page) => $this->getList($userId, $page, $perPage, $primaryPhotoExtras, $photoIds, $sortGroups),
            'photoset'
        );
    }

    public function getPhotos(
        string $userId,
        string $photosetId,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = [],
        ?PrivacyLevel $privacyFilter = null,
        ?MediaType $mediaType = null
    ): ApiResponse {
        $params = [
            'user_id' => $userId,
            'photoset_id' => $photosetId,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);

        if (\count($extras) !== 0) {
            $params['extras'] = $this->serializeExtras($extras);
        }

        if ($privacyFilter !== null) {
            $params['privacy_filter'] = $privacyFilter->value;
        }

        if ($mediaType !== null) {
            $params['media'] = $mediaType->value;
        }

        return $this->client->call('flickr.photosets.getPhotos', $params, 'photoset');
    }

    public function getPhotosIterable(
        string $userId,
        string $photosetId,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = [],
        ?PrivacyLevel $privacyFilter = null,
        ?MediaType $mediaType = null
    ): iterable
    {
        return $this->flattenPages(
            fn(int $page) => $this->getPhotos(
                $userId,
                $photosetId,
                $page,
                $perPage,
                $extras,
                $privacyFilter,
                $mediaType
            ),
            'photo'
        );
    }

    public function getInfo(string $userId, string $photosetId): ApiResponse
    {
        return $this->client->call(
            'flickr.photosets.getInfo',
            ['user_id' => $userId, 'photoset_id' => $photosetId],
            'photoset'
        );
    }
}
