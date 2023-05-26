<?php
declare(strict_types=1);

namespace App\Flickr\Client;

use App\Exception\Api\BadApiMethodCallException;
use App\Exception\Api\TransportException;
use App\Flickr\ClientEndpoint\FavoritesEndpoint;
use App\Flickr\ClientEndpoint\PandaEndpoint;
use App\Flickr\ClientEndpoint\PhotosEndpoint;
use App\Flickr\ClientEndpoint\PhotosetsEndpoint;
use App\Flickr\ClientEndpoint\TestEndpoint;
use App\Flickr\ClientEndpoint\UrlsEndpoint;
use App\Flickr\Struct\ApiResponse;
use App\Flickr\Url\UrlGenerator;
use App\Flickr\Url\UrlParser;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class FlickrApiClient implements ServiceSubscriberInterface
{
    private readonly FavoritesEndpoint $favorites;
    private readonly PandaEndpoint $panda;
    private readonly PhotosEndpoint $photos;
    private readonly PhotosetsEndpoint $photosets;
    private readonly TestEndpoint $test;
    private readonly UrlsEndpoint $urls;

    public function __construct(
        private ContainerInterface $locator,
        private HttpClientInterface $httpClient,
        private ApiClientConfig $config,
        private ?LoggerInterface $flickrApiLogger
    ) {
    }

    public function getFavorites(): FavoritesEndpoint
    {
        return $this->favorites ??= new FavoritesEndpoint($this);
    }

    public function getPanda(): PandaEndpoint
    {
        return $this->panda ??= new PandaEndpoint($this);
    }
    
    public function getPhotos(): PhotosEndpoint
    {
        return $this->photos ??= new PhotosEndpoint($this);
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
        return $this->urls ??= new UrlsEndpoint(
            $this,
            $this->locator->get(UrlGenerator::class),
            $this->locator->get(UrlParser::class)
        );
    }
    
    public function call(string $method, array $params, ?string $envelope = null): ApiResponse
    {
        $requestOpts = $this->buildHttpClientOptions($method, $params, $envelope);
        $this->logCall($method, $params, $envelope, $requestOpts);

        try {
            $response = $this->httpClient->request('GET', 'https://www.flickr.com/services/rest', $requestOpts);
            $rawContent = $response->toArray();
        } catch (\Throwable $t) {
            $this->logCall($method, $params, $envelope, $requestOpts, 'transport error "' . $t->getMessage() . '"');
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

    public function rawHttpCall(string $httpMethod, string $url, array $queryParams = [], bool $wrapExceptions = true): ResponseInterface
    {
        $requestOpts = $this->config->httpConfig->asOptions();
        $requestOpts['query'] = $queryParams;

        $paramsTxt = [];
        foreach ($queryParams as $k => $v) {
            $paramsTxt[] = "$k=$v";
        }
        $logMsg = 'Raw HTTP via API client to {url} <{args}>';
        $logParams = ['url' => $url, 'args' => \implode(', ', $paramsTxt)];
        $this->flickrApiLogger->debug($logMsg, $logParams);

        if (!$wrapExceptions) {
            return $this->httpClient->request($httpMethod, $url, $requestOpts);
        }

        try {
            return $this->httpClient->request($httpMethod, $url, $requestOpts);
        } catch (\Throwable $t) {
            $this->flickrApiLogger->debug($logMsg . ' FAILED: transport error "' . $t->getMessage() . '"', $logParams);

            throw TransportException::create(
                \sprintf(
                    'HTTP call to API url "%s" failed with %s: %s ',
                    $url,
                    (new \ReflectionClass($t::class))->getShortName(),
                    $t->getMessage()
                ),
                $t
            );
        }
    }

    private function logCall(string $method, array $params, ?string $envelope, ?array $httpConfig, ?string $error = null): void
    {
        if ($this->flickrApiLogger === null) {
            return;
        }

        $paramsTxt = [];
        foreach ($params as $k => $v) {
            $paramsTxt[] = "$k=$v";
        }

        $msg = 'Call {mthd}({args})[{env}]';
        $ctx = [
            'mthd' => $method,
            'args' => \implode(', ', $paramsTxt),
            'env' => $envelope,
        ];

        if ($httpConfig !== null) {
            $msg .= ' via key={key} ua={ua} prx={prx}';
            $ctx['key'] = $httpConfig['query']['api_key'];
            $ctx['ua'] = $httpConfig['headers']['User-Agent'] ?? '???';
            $ctx['prx'] = $httpConfig['proxy'] ?? null;
        }

        if ($error !== null) {
            $msg .= ' FAILED: {msg}';
            $ctx['msg'] = $error;

            $this->flickrApiLogger->error($msg, $ctx);
        } else {
            $this->flickrApiLogger->debug($msg, $ctx);
        }

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

    private function buildHttpClientOptions(string $apiMethod, array $userParams, ?string $envelope): array
    {
        if (!\str_starts_with($apiMethod, 'flickr.')) {
            $this->logCall($apiMethod, $userParams, $envelope, null, 'invalid method name');
            throw new BadApiMethodCallException(\sprintf('Invalid method name "%s"', $apiMethod));
        }

        if (isset($userParams['method']) || isset($userParams['api_key']) ||
            isset($userParams['format']) || isset($userParams['nojsoncallback'])) {
            $this->logCall($apiMethod, $userParams, $envelope, null, 'reserved parameters passed');
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

    public static function getSubscribedServices(): array
    {
        return [
            UrlGenerator::class, //used only for Urls endpoint
            UrlParser::class, //used only for Urls endpoint
        ];
    }
}
