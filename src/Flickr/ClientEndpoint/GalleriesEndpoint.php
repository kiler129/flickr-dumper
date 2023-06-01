<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Exception\Api\BadApiMethodCallException;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Enum\PhotoSize;
use App\Flickr\Struct\ApiResponse;

/**
 * Galleries endpoint, manipulating curated collection of photos created by a user from photos taken by OTHER users
 */
class GalleriesEndpoint
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.galleries.getList.html
     * This limit doesn't seem to be enforced (i.e. using >500 returns >500 results).
     */
    public const MAX_PER_PAGE = 500;

    public function __construct(private FlickrApiClient $client)
    {
    }

    /**
     * Returns lists of user galleries. BEFORE USING READ the full description!
     *
     *
     * Note: this method's description in the API docs https://www.flickr.com/services/api/flickr.galleries.getList.html
     * is incomplete/confusing and partially incorrect. Parameters were renamed to be less confusing. In addition,
     * there are three ways to paginate results:
     *   - using normal page/perPage schema
     *   - using continuation token and perPage
     *   - not paginating at all
     *
     * Page/perPage pagination
     * -----------------------
     * In raw API this works *only* when both page and perPage are specified. If *either* of them is empty the API will
     * return the full set of results with {page: 1, pages: 1, per_page: <total count>, total: <total count>}. The docs
     * incorrectly states that perPage defaults to 100 - it does not.
     * Thus, first request should be made with page=1. This isn't a concern with this API client as it forces the
     * "page" to be set.
     *
     * Continuation token pagination
     * -----------------------------
     * Presumably to offer infinite scrolling and/or do some clever SQL optimization, the API offers pagination without
     * strict page boundaries. You should send $continuation=CONTINUATION_START_TOKEN to enable this mode. Then, API
     * responds with some results and "continuation" field (contains some internal encoded JSON). Value of that
     * continuation field must be passed in the subsequent request. When server returns "continuation" equal to
     * CONTINUATION_LAST_TOKEN we can assume there are no more results.
     * This method of pagination does NOT require "perPage" being set in the call - it properly defaults to 100.
     * Warning: before comparing "continuation" to anything MAKE SURE to cast it to string as sometimes it comes as
     * int.
     *
     * No pagination at all
     * --------------------
     * This is most likely a bug when neither continuation nor perPage+page are set. While it works and returns results
     * without pagination, it's most likely a bug. Relying on it and DoSing Flickr API is a bad idea... thus this API
     * client doesn't allow you to do that. Also, the API will often timeout with bigger results.
     *
     *
     * @param string         $userId                         User NSID to fetch the list from
     * @param int|null       $page                           Page to return; null to use "continuation token"
     *                                                       pagination (default; faster)
     * @param string|null    $continuationToken              Continuation token for iteration; defaults to
     *                                                       CONTINUATION_START_TOKEN as it's faster and some options
     *                                                       only work when such pagination isy used. Set to null to
     *                                                       use $page-style pagination.
     * @param int            $perPage                        Number of records to return at max per request
     * @param list<string>   $primaryPhotoExtras             List of fields to return for primary photo; see
     *                                                       Flickr\Enum\PhotoExtraFields for details.
     * @param list<int>      $photoIds                       List of photo ids; see API docs
     * @param array          $sortGroups                     List of groups; see API docs
     * @param bool           $includeCoverPhotos             Whether to include cover photos in response; see
     *                                                       "cover_photos" API docs for details. When enabled it will
     *                                                       return 1 cover photo if gallery has primary photo set it
     *                                                       will return that primary photo +
     *                                                       $addtlCoverPhotosForWithPrimary; if no primary photo is
     *                                                       set it will return $coverPhotosForWithoutPrimary photos as
     *                                                       covers; defaults to false.
     * @param PhotoSize|null $coverSizePrimary               Size of cover for primary photo returned (if gallery has
     *                                                       one); see "primary_photo_cover_size" API docs for details.
     *                                                       This option is only allowed when $includeCoverPhotos=true
     * @param PhotoSize|null $coverSizeAddtl                 Size of cover photos returned for all but primary photo;
     *                                                       see "cover_photos_size" API docs for details. This option
     *                                                       is only allowed when $includeCoverPhotos=true
     * @param int|null       $addtlCoverPhotosForWithPrimary Number of additional (on top of the primary photo) cover
     *                                                       photos to include for galleries WITH primary photo set.
     *                                                       See "short_limit" API docs. This option is only allowed
     *                                                       when $includeCoverPhotos=true
     * @param int|null       $coverPhotosForWithoutPrimary   Number of cover photos to include for galleries WITHOUT
     *                                                       primary photo set. See "limit" This option is only allowed
     *                                                       when $includeCoverPhotos=true
     *
     * @return ApiResponse
     */
    public function getList(
        string $userId,
        int|null $page = null,
        string|null $continuationToken = self::CONTINUATION_START_TOKEN,
        int $perPage = self::MAX_PER_PAGE,
        array $primaryPhotoExtras = [],
        array $photoIds = [],
        array $sortGroups = [],
        bool $includeCoverPhotos = false,
        PhotoSize|null $coverSizePrimary = null,
        PhotoSize|null $coverSizeAddtl = null,
        int|null $addtlCoverPhotosForWithPrimary = null,
        int|null $coverPhotosForWithoutPrimary = null,
    ): ApiResponse {
        $params = [
            'user_id' => $userId,
            'per_page' => $perPage,
        ];

        $this->validateTokenPaginationValues($page, $perPage, $continuationToken);

        if (\count($primaryPhotoExtras) !== 0) {
            $params['primary_photo_extras'] = $this->serializeExtras($primaryPhotoExtras);
        }

        if (\count($photoIds) !== 0) {
            $params['photo_ids'] = \implode(',', $photoIds);
        }

        if (\count($sortGroups) !== 0) {
            if ($continuationToken === null) {
                throw new BadApiMethodCallException('Using sortGroups is only possible with token-based pagination');
            }
            $params['sort_groups'] = \implode(',', $sortGroups);
        }

        if ($includeCoverPhotos) {
            $params['cover_photos'] = 1;

            if ($coverSizePrimary !== null) {
                $params['primary_photo_cover_size'] = $coverSizePrimary->value;
            }
            if ($coverSizeAddtl !== null) {
                $params['cover_photos_size'] = $coverSizeAddtl->value;
            }
            if ($addtlCoverPhotosForWithPrimary !== null) {
                $params['short_limit'] = $addtlCoverPhotosForWithPrimary;
            }
            if ($coverPhotosForWithoutPrimary !== null) {
                $params['limit'] = $addtlCoverPhotosForWithPrimary;
            }
        } else {
            if ($coverSizePrimary !== null) {
                throw new BadApiMethodCallException(
                    \sprintf(
                        'Cannot use coverSizePrimary (got %s) when includeCoverPhotos is disabled',
                        $coverSizePrimary->name
                    )
                );
            }
            if ($coverSizeAddtl !== null) {
                throw new BadApiMethodCallException(
                    \sprintf(
                        'Cannot use coverSizeAddtl (got %s) when includeCoverPhotos is disabled',
                        $coverSizeAddtl->name
                    )
                );
            }
            if ($addtlCoverPhotosForWithPrimary !== null) {
                throw new BadApiMethodCallException(
                    \sprintf(
                        'Cannot use addtlCoverPhotosForWithPrimary (got %d) when includeCoverPhotos is disabled',
                        $addtlCoverPhotosForWithPrimary
                    )
                );
            }
            if ($coverPhotosForWithoutPrimary !== null) {
                throw new BadApiMethodCallException(
                    \sprintf(
                        'Cannot use coverPhotosForWithoutPrimary (got %d) when includeCoverPhotos is disabled',
                        $coverPhotosForWithoutPrimary
                    )
                );
            }
        }

        return $this->client->call('flickr.galleries.getList', $params, 'galleries');
    }

    /**
     * Returns a continuous/unpaged stream of user galleries. BEFORE USING READ the full description of getList()!
     */
    public function getListIterable(
        string $userId,
        int $perPage = self::MAX_PER_PAGE,
        array $primaryPhotoExtras = [],
        array $photoIds = [],
        array $sortGroups = [],
        bool $includeCoverPhotos = false,
        PhotoSize|null $coverSizePrimary = null,
        PhotoSize|null $coverSizeAddtl = null,
        int|null $addtlCoverPhotosForWithPrimary = null,
        int|null $coverPhotosForWithoutPrimary = null,
        ?callable $pageFinishCallback = null,
    ): iterable {
        return $this->flattenPagesTokenized(
            fn(string $continuationToken) => $this->getList(
                $userId,
                null,
                $continuationToken,
                $perPage,
                $primaryPhotoExtras,
                $photoIds,
                $sortGroups,
                $includeCoverPhotos,
                $coverSizePrimary,
                $coverSizeAddtl,
                $addtlCoverPhotosForWithPrimary,
                $coverPhotosForWithoutPrimary
            ),
            $pageFinishCallback,
            'gallery'
        );
    }

    /**
     * Get photos in a user gallery
     *
     * Note: the documentation for this method (https://www.flickr.com/services/api/flickr.galleries.getPhotos.html) is
     * wrong. While it claims it supports token-based pagination ("continuation") it does not. Sending anything in that
     * field along with perPage being set to anything will result in a response with "page" being null but no
     * "continuation" field is present, making the pagination useless.
     * Given that, this method does not implement $continuationToken parameter.
     *
     *
     * @param string $userId User NSID of the gallery owner
     * @param string $galleryId Gallery ID to be fetched
     * @param int    $page Page number to return; defaults to 1
     * @param int    $perPage Number of records per page to return; defaults to max allowed
     * @param array  $extras Additional fields to return for each photo; see Flickr\Enum\PhotoExtraFields for details
     * @param bool   $includeOwnerInfo Whether to include GALLERY owner/user details (not per photo!)
     * @param bool   $includeGalleryInfo Whether to include gallery information
     *
     * @return ApiResponse
     */
    public function getPhotos(
        string $userId,
        string $galleryId,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = [],
        bool $includeOwnerInfo = false,
        bool $includeGalleryInfo = false,
    ): ApiResponse {
        $params = [
            'user_id' => $userId,
            'gallery_id' => $galleryId,
            'page' => $page,
            'per_page' => $perPage,
        ];

        $this->validateRegularPaginationValues($page, $perPage);

        if (\count($extras) !== 0) {
            $params['extras'] = $this->serializeExtras($extras);
        }

        if ($includeOwnerInfo) {
            $params['get_user_info'] = 1;
        }

        if ($includeGalleryInfo) {
            $params['get_gallery_info'] = 1;
        }

        return $this->client->call('flickr.galleries.getPhotos', $params, 'photos');
    }

    /**
     * Returns a continuous/unpaged stream of photos in a gallery. BEFORE USING READ the full description of getPhotos()
     */
    public function getPhotosIterable(
        string $userId,
        string $galleryId,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = [],
        bool $includeOwnerInfo = false,
        bool $includeGalleryInfo = false,
        ?callable $pageFinishCallback = null,
    ): iterable
    {
        return $this->flattenRegularPages(
            fn(int $page) => $this->getPhotos(
                $userId,
                $galleryId,
                $page,
                $perPage,
                $extras,
                $includeOwnerInfo,
                $includeGalleryInfo
            ),
            $pageFinishCallback,
            'photo',
        );
    }

    /**
     * Returns information about a gallery
     *
     * @param string         $userId                         User NSID of the gallery owner
     * @param string         $galleryId                      Gallery ID to be fetched
     * @param PhotoSize|null $coverSizePrimary               Size of cover for primary photo returned (if gallery has
     *                                                       one); see "primary_photo_cover_size" API docs for details.
     *                                                       This option is only allowed when $includeCoverPhotos=true
     * @param PhotoSize|null $coverSizeAddtl                 Size of cover photos returned for all but primary photo;
     *                                                       see "cover_photos_size" API docs for details. This option
     *                                                       is only allowed when $includeCoverPhotos=true
     * @param int|null       $addtlCoverPhotosForWithPrimary Number of additional (on top of the primary photo) cover
     *                                                       photos to include for galleries WITH primary photo set.
     *                                                       See "short_limit" API docs. By default, it will 6.
     * @param int|null       $coverPhotosForWithoutPrimary   Number of cover photos to include for galleries WITHOUT
     *                                                       primary photo set. See "limit". By default, it will return
     *                                                       2.
     *
     * @return ApiResponse
     */
    public function getInfo(
        string $userId,
        string $galleryId,
        PhotoSize|null $coverSizePrimary = null,
        PhotoSize|null $coverSizeAddtl = null,
        int|null $addtlCoverPhotosForWithPrimary = null,
        int|null $coverPhotosForWithoutPrimary = null,
    ): ApiResponse
    {
        $params = [
            'user_id' => $userId,
            'gallery_id' => $galleryId,
        ];

        if ($coverSizePrimary !== null) {
            $params['primary_photo_size'] = $coverSizePrimary->value;
        }
        if ($coverSizeAddtl !== null) {
            $params['cover_photos_size'] = $coverSizeAddtl->value;
        }
        if ($addtlCoverPhotosForWithPrimary !== null) {
            $params['short_limit'] = $addtlCoverPhotosForWithPrimary;
        }
        if ($coverPhotosForWithoutPrimary !== null) {
            $params['limit'] = $addtlCoverPhotosForWithPrimary;
        }

        return $this->client->call('flickr.galleries.getInfo', $params, 'gallery');
    }
}
