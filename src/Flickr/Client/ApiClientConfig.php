<?php
declare(strict_types=1);

namespace App\Flickr\Client;

use App\Struct\HttpClientConfig;

readonly final class ApiClientConfig
{
    public function __construct(
        public string $apiKey,
        public HttpClientConfig $httpConfig
    ) {
    }

    //public function getHash(): string
    //{
    //    return sha1($this->httpConfig->getHash() . '/' . $this->apiKey);
    //}
}
