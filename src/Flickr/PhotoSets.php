<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\UnexpectedResponseException;
use App\Flickr\ClientEndpoint\ApiEndpointHelper;
use App\Struct\MediaType;
use App\Struct\PhotoExtraFields;

/**
 * Photosets aka albums
 *
 * Note: some of the Flickr docs are incorrect (e.g. regarding pagination being optional)
 */
class PhotoSets
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.photosets.getList.html
     */
    public const MAX_PER_PAGE = 500;


    private BaseApiClient $baseClient;

    public function __construct(BaseApiClient $baseClient)
    {
        $this->baseClient = $baseClient;
    }

    public function iterateListFlat(string $userId, int $perPage = self::MAX_PER_PAGE): iterable
    {
        $page = 1;
        $totalPages = null;

        do {
            $rsp = $this->getList($userId, $page, $perPage);
            if ($totalPages === null) {
                if (!isset($rsp['pages'])) {
                    throw UnexpectedResponseException::create('API did not return number of pages', $rsp);
                }

                $totalPages = (int)$rsp['pages'];
            }

            if (!isset($rsp['photoset'])) {
                throw UnexpectedResponseException::create(
                    \sprintf('API did not return any sets for %d page', $page),
                    $rsp
                );
            }

            yield from $rsp['photoset'];
        } while (++$page <= $totalPages);
    }

    public function getList(
        string $userId,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $primaryPhotoExtras = [],
        array $photoIds = [],
        array $sortGroups = [],
    ): array {
        $params = [
            'user_id' => $userId,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);

        if (!empty($primaryPhotoExtras)) {
            if (\count($primaryPhotoExtras) !== \count(\array_intersect($primaryPhotoExtras, PhotoExtraFields::ALL))) {
                throw new BadApiMethodCallException(
                    'Invalid primaryPhotoExtras value detected'
                ); //TODO add which were wrong
            }

            $params['primary_photo_extras'] = \implode(',', $primaryPhotoExtras);
        }

        if (!empty($photoIds)) {
            $params['photo_ids'] = \implode(',', $photoIds);
        }

        if (!empty($sortGroups)) {
            $params['sort_groups'] = \implode(',', $sortGroups);
        }

        $rsp = $this->baseClient->callMethod('flickr.photosets.getList', $params);
        if (!isset($rsp['photosets'])) {
            throw UnexpectedResponseException::create('API did not return photosets', $rsp);
        }

        return $rsp['photosets'];
    }

    public function iteratePhotosFlat(
        string $userId,
        string $photosetId,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = []
    ): iterable {
        $page = 1;
        $totalPages = null;

        do {
            $rsp = $this->getPhotos($userId, $photosetId, $page, $perPage, $extras);
            if ($totalPages === null) {
                if (!isset($rsp['pages'])) {
                    throw UnexpectedResponseException::create('API did not return number of pages', $rsp);
                }

                $totalPages = (int)$rsp['pages'];
            }

            if (!isset($rsp['photo'])) {
                throw UnexpectedResponseException::create(
                    \sprintf('API did not return any photos for %d page', $page),
                    $rsp
                );
            }

            yield from $rsp['photo'];
        } while (++$page <= $totalPages);
    }

    public function getPhotos(
        string $userId,
        string $photosetId,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = [],
        ?int $privacyFilter = null,
        ?string $mediaType = null
    ): array {
        $params = [
            'user_id' => $userId,
            'photoset_id' => $photosetId,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);
        $this->normalizeExtrasToParams($params, $extras);

        if ($privacyFilter !== null) {
            $params['privacy_filter'] = $privacyFilter;
        }

        if ($mediaType !== null) {
            if (!isset(MediaType::ALL[$mediaType])) {
                throw new BadApiMethodCallException(\sprintf('"%s" is not a valid media type', $mediaType));
            }

            $params['media'] = $mediaType;
        }

        $rsp = $this->baseClient->callMethod('flickr.photosets.getPhotos', $params);
        if (!isset($rsp['photoset'])) {
            throw UnexpectedResponseException::create('API did not return photoset', $rsp);
        }

        return $rsp['photoset'];
    }

    public function getInfo(string $userId, string $photosetId): array
    {
        return $this->baseClient->fetchResult('flickr.photosets.getInfo', 'photoset', [
            'user_id' => $userId,
            'photoset_id' => $photosetId,
        ]);
    }
}
