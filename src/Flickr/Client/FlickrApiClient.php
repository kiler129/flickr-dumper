<?php
declare(strict_types=1);

namespace App\Flickr\Client;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\TransportException;
use App\Flickr\ClientEndpoint\PandaEndpoint;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\ClientEndpoint\TestEndpoint;
use App\Flickr\ClientEndpoint\UrlsEndpoint;
use App\Flickr\Struct\ApiResponse;use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FlickrApiClient
{
    public const DEFAULT_MAX_PER_PAGE = 500;

    private readonly PandaEndpoint $panda;
    private readonly PhotosetsEndpoint $photosets;
    private readonly TestEndpoint $test;
    private readonly UrlsEndpoint $urls;

    public function __construct(private HttpClientInterface $httpClient, private ApiClientConfig $config)
    {
    }

    public function getPanda(): PandaEndpoint
    {
        return $this->panda ??= new PandaEndpoint($this);
    }

    public function getPhotosets(): PhotosetsEndpoint
    {
        return $this->photosets ??= new PhotosetsEndpoint($this);
    }

    public function getTest(): TestEndpoint
    {
        return $this->test ??= new TestEndpoint($this);
    }

    public function getUrls(): UrlsEndpoint
    {
        return $this->urls ??= new UrlsEndpoint($this);
    }
    
    public function call(string $method, array $params, ?string $envelope = null): ApiResponse
    {
        $requestOpts = $this->buildHttpClientOptions($method, $params);
        $response = $this->httpClient->request('GET', 'https://www.flickr.com/services/rest', $requestOpts);

        try {
            $rawContent = $response->toArray();
        } catch (\Throwable $t) {
            throw TransportException::create(
                \sprintf(
                    'HTTP call to API failed with %s: %s ',
                    (new \ReflectionClass($t::class))->getShortName(),
                    $t->getMessage()
                ),
                $t
            );
        }

        return new ApiResponse($rawContent, $envelope, $response);
    }

    /**
     * Provides a new instance of API client with a new configuration
     *
     * This is almost the same as creating a new instance but saves HTTP client and all endpoints. The setter
     *
     * @param ApiClientConfig $config
     *
     * @return $this
     */
    public function withConfiguration(ApiClientConfig $config): self
    {
        $client = clone $this;
        $client->config = $config;

        return $client;
    }

    private function buildHttpClientOptions(string $apiMethod, array $userParams): array
    {
        if (!\str_starts_with($apiMethod, 'flickr.')) {
            throw new BadApiMethodCallException(\sprintf('Invalid method name "%s"', $apiMethod));
        }

        if (isset($userParams['method']) || isset($userParams['api_key']) ||
            isset($userParams['format']) || isset($userParams['nojsoncallback'])) {
            throw new BadApiMethodCallException(
                'Parameters array should not contain reserved keywords: method, api_key, format, nojsoncallback. ' .
                'Found: ' . \implode('", "', \array_keys($userParams))
            );
        }

        $options = $this->config->httpConfig->asOptions();
        $options['query'] = $userParams;
        $options['query']['method'] = $apiMethod;
        $options['query']['api_key'] = $this->config->apiKey;
        $options['query']['format'] = 'json';
        $options['query']['nojsoncallback'] = '1'; //by default, it will return JSONP

        return $options;
    }
}
