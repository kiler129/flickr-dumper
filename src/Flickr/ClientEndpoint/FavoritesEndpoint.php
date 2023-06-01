<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

/**
 * Favorites / photos favorited or stared by a user
 *
 * Note: some of the Flickr docs are incorrect (e.g. regarding pagination being optional)
 */
class FavoritesEndpoint
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.favorites.getList.html
     */
    public const MAX_PER_PAGE = 500;

    public function __construct(private FlickrApiClient $client)
    {
    }

    public function getList(
        string $userId,
        ?\DateTimeInterface $minDate = null,
        ?\DateTimeInterface $maxDate = null,
        array $extras = [],
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
    ): ApiResponse {
        $params = [
            'user_id' => $userId,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validateRegularPaginationValues($page, $perPage);

        if ($minDate !== null) {
            $params['min_fave_date'] = $minDate->getTimestamp();
        }

        if ($maxDate !== null) {
            $params['max_fave_date'] = $maxDate->getTimestamp();
        }

        if (\count($extras) !== 0) {
            $params['extras'] = $this->serializeExtras($extras);
        }

        return $this->client->call('flickr.favorites.getList', $params, 'photos');
    }

    public function getListIterable(
        string $userId,
        ?\DateTimeInterface $minDate = null,
        ?\DateTimeInterface $maxDate = null,
        array $extras = [],
        int $perPage = self::MAX_PER_PAGE,
        ?callable $pageFinishCallback = null,
    ): iterable {
        return $this->flattenRegularPages(
            fn(int $page) => $this->getList($userId, $minDate, $maxDate, $extras, $page, $perPage),
            $pageFinishCallback,
            'photo'
        );
    }
}
