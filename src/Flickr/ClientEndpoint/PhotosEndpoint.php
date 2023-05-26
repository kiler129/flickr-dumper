<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;

final readonly class PhotosEndpoint
{
    use ApiEndpointHelper;

    public function __construct(private FlickrApiClient $client)
    {
    }

    public function getInfo(int $photoId, ?string $secret = null): ApiResponse
    {
        $params = [
            'photo_id' => $photoId
        ];

        if ($secret !== null) {
            $params['secret'] = $secret;
        }

        return $this->client->call('flickr.photos.getInfo', $params, 'photo');
    }
}
