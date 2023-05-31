<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

class PeopleEndpoint
{
    public function __construct(private FlickrApiClient $client) {
    }

    /**
     * Finds user info by their NSID
     *
     * @param string $nsid
     *
     * @return ApiResponse
     * @see https://www.flickr.com/services/api/flickr.people.getInfo.html
     */
    public function lookupUser(string $nsid): ApiResponse
    {
        return $this->client->call('flickr.people.getInfo', ['user_id' => $nsid], 'person');
    }
}
