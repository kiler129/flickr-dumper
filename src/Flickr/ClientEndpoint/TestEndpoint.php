<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

class TestEndpoint
{
    public function __construct(private FlickrApiClient $client)
    {
    }

    /**
     * Calls flickr.test.echo
     *
     * @return array
     */
    public function echo(): ApiResponse
    {
        return $this->client->call('flickr.test.echo', []);
    }

    public function null(): ApiResponse
    {
        return $this->client->call('flickr.test.null', []);
    }
}
