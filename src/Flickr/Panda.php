<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Flickr\ClientEndpoint\ApiEndpointHelper;

/**
 * See http://code.flickr.com/blog/2009/03/03/panda-tuesday-the-history-of-the-panda-new-apis-explore-and-you/
 */
class Panda
{
    use ApiEndpointHelper;

    /**
     * As documented on https://www.flickr.com/services/api/flickr.panda.getPhotos.htm
     */
    private const MAX_PER_PAGE = 500;

    public function __construct(private BaseApiClient $baseClient)
    {
    }

    public function getList(): array {
        $pandas = $this->baseClient->fetchResult('flickr.panda.getList', 'pandas');

        return \array_column($pandas['panda'] ?? [], '_content');
    }

    public function getPhotos(
        string $pandaName,
        int $page = 1,
        int $perPage = self::MAX_PER_PAGE,
        array $extras = []
    ): array {
        $params = [
            'panda_name' => $pandaName,
            'per_page' => $perPage,
            'page' => $page,
        ];

        $this->validatePaginationValues($page, $perPage);
        $this->normalizeExtrasToParams($params, $extras);

        return $this->baseClient->fetchResult('flickr.panda.getPhotos', 'pandas', $params);
    }
}
