<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\UnexpectedResponseException;

class Urls
{
    private BaseApiClient $baseClient;

    public function __construct(BaseApiClient $baseClient)
    {
        $this->baseClient = $baseClient;
    }

    public function lookupUserId(string $url): string
    {
        $rsp = $this->lookupUser($url);
        if (!isset($rsp['user']['id'])) {
            throw UnexpectedResponseException::create('user->id is missing from response', $rsp);
        }

        return (string)$rsp['user']['id'];
    }

    public function lookupUser(string $url): array
    {
        return $this->baseClient->callMethod('flickr.urls.lookupUser', ['url' => $url]);
    }

    /** @deprecated use Url\UrlParser::getPhotosetIdentity) */
    public function getPhotosetIdFromUrl(string $url): string
    {
        //Flickr doesn't have API method to do this so... well...
        if (\preg_match('/^htt.*flickr\.com\/photos\/.*\/albums\/(.*)(?:\/|$)/', $url, $matches) !== 1) {
            throw new BadApiMethodCallException('URL is invalid');
        }

        return $matches[1];
    }
}
