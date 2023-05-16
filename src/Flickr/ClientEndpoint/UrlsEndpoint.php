<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

class UrlsEndpoint
{
    public function __construct(private FlickrApiClient $client)
    {
    }

    /**
     * Finds user (NSID & username) by URL to their profile or photos page
     *
     * @param string $url
     *
     * @return ApiResponse
     * @see https://www.flickr.com/services/api/flickr.urls.lookupUser.htm
     */
    public function lookupUser(string $url): ApiResponse
    {
        return $this->client->call('flickr.urls.lookupUser', ['url' => $url], 'user');
    }
}
