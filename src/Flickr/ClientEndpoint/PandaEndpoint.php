<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

/**
 * See http://code.flickr.com/blog/2009/03/03/panda-tuesday-the-history-of-the-panda-new-apis-explore-and-you/
 * This API is pretty much their public sandbox ;D
 */
final readonly class PandaEndpoint
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.panda.getPhotos.html
     */
    private const MAX_PER_PAGE = 500;

    public function __construct(private FlickrApiClient $client)
    {
    }

    public function getList(): ApiResponse
    {
        return $this->client->call('flickr.panda.getList', [], 'pandas');
    }

    public function getPhotos(
        string $pandaName,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = []
    ): ApiResponse {
        $params = [
            'panda_name' => $pandaName,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);
        $this->normalizeExtrasToParams($params, $extras);

        return $this->client->call('flickr.panda.getPhotos', $params, 'pandas');
    }
}
