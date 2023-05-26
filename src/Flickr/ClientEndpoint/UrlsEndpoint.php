<?php
declare(strict_types=1);

namespace App\Flickr\ClientEndpoint;

use App\Exception\Api\TransportException;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Struct\ApiResponse;
use App\Flickr\Struct\Identity\MediaIdentity;
use App\Flickr\Url\UrlGenerator;
use App\Flickr\Url\UrlParser;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;

class UrlsEndpoint
{
    public function __construct(
        private FlickrApiClient $client,
        private UrlGenerator $urlGen,
        private UrlParser $urlParse
    ) {
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

    /**
     * [UNDOCUMENTED] gets URL of a photo based on its id only
     *
     * @param string $url
     *
     * @return string|null Url for the photo or null if not found/unable to resolve
     */
    public function lookupMediaById(int $photoId): ?MediaIdentity
    {
        $url = $this->urlGen->getPhotoViewLinkById($photoId);
        //$max = 10;
        //while($max-- > 0) {
            $response = $this->client->rawHttpCall('HEAD', $url);
            dd($response);
        //}

        //throw new TransportException('Failed to get media identity by URL - redirect limit reached');
    }
}
