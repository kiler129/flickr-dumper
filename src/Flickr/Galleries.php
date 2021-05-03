<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\ApiCallException;
use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\UnexpectedResponseException;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoSize;

class Galleries
{
    /**
     * As per docs: "The first request must pass the "continuation" parameter with the value of "0""
     */
    private const NULL_CONTINUATION_TOKEN = '0';

    /**
     * As documented on https://www.flickr.com/services/api/flickr.galleries.getList.html
     */
    private const MAX_PER_PAGE = 500;


    private BaseApiClient $baseClient;

    public function __construct(BaseApiClient $baseClient)
    {
        $this->baseClient = $baseClient;
    }

    /**
     * @param string      $userId
     * @param int         $perPage
     * @param int         $page
     * @param array       $primaryPhotoExtras See \App\Struct\PhotoExtraFields
     * @param string      $continuationToken
     * @param array|null  $sortGroups
     * @param array|null  $photoIds
     * @param bool        $coverPhotos
     * @param string|null $primaryCoverPhoto
     * @param string|null $coverPhotosSize
     * @param int         $coverPhotoLimit
     * @param int         $shortCoverPhotoLimit
     *
     * @return array
     */
    public function getList(
        string $userId,
        int $perPage = 100,
        int $page = 1,
        array $primaryPhotoExtras = [],
        string $continuationToken = self::NULL_CONTINUATION_TOKEN,
        array $sortGroups = [],
        array $photoIds = [],
        bool $coverPhotos = false,
        ?string $primaryCoverPhotoSize = null,
        ?string $coverPhotosSize = null,
        ?int $coverPhotosLimit = null,
        ?int $shortCoverPhotoLimit = null
    ): array
    {
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new BadApiMethodCallException(\sprintf('Per page must be between 1 and %d (got %d)', self::MAX_PER_PAGE, $perPage));
        }

        if ($page < 1) {
            throw new BadApiMethodCallException(\sprintf('Page # must be a positive integer (got %d)', $page));
        }

        $params = [
            'user_id' => $userId,
            'per_page' => $perPage,
            'page' => $page,
            'continuation' => $continuationToken,
        ];

        if (!empty($primaryPhotoExtras)) {
            if (\count($primaryPhotoExtras) !== \count(\array_intersect($primaryPhotoExtras, PhotoExtraFields::ALL))) {
                throw new BadApiMethodCallException('Invalid primaryPhotoExtras value detected'); //TODO add which were wrong
            }

            $params['primary_photo_extras'] = \implode(',', $primaryPhotoExtras);
        }

        if (!empty($sortGroups)) {
            $params['sort_groups'] = \implode(',', $sortGroups);
        }

        if (!empty($photoIds)) {
            $params['photo_ids'] = \implode(',', $photoIds);
        }

        if ($coverPhotos) {
            $params['cover_photos'] = 1;
        }

        if ($primaryCoverPhotoSize !== null) {
            if (!isset(PhotoSize::ALL[$primaryCoverPhotoSize])) {
                throw new BadApiMethodCallException(\sprintf('"%s" is not a valid photo size', $primaryCoverPhotoSize));
            }

            $params['primary_photo_cover_size'] = $primaryCoverPhotoSize;
        }

        if ($coverPhotosSize !== null) {
            if (!isset(PhotoSize::ALL[$coverPhotosSize])) {
                throw new BadApiMethodCallException(\sprintf('"%s" is not a valid photo size', $coverPhotosSize));
            }

            $params['cover_photos_size'] = $coverPhotosSize;
        }

        if ($coverPhotosLimit !== null) {
            $params['limit'] = $coverPhotosLimit;
        }

        if ($shortCoverPhotoLimit !== null) {
            $params['short_limit'] = $shortCoverPhotoLimit;
        }

        return $this->baseClient->callMethod('flickr.galleries.getList', $params);
    }
}
