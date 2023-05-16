<?php
declare(strict_types=1);

namespace App\Factory;

use App\Exception\RuntimeException;
use App\Flickr\ApiKeyProvider;
use App\Util\AgentIdentityProvider;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Creates various HTTP clients
 *
 * This works similarly to the standard Symfony scoping HttpClient, but it doesn't discriminate by URI. Instead, it
 * always uses given options when injected.
 *
 * @deprecated
 */
final class HttpClientFactory
{
    public function __construct(
        private ?string $flickrApiProxy,
        private ?string $flickrDownloadProxy,
        private ApiKeyProvider $apiKeyProvider,
        private AgentIdentityProvider $uaProvider,
    ) {
    }

    public function createApiHttpClient(): HttpClientInterface
    {
        $opts = [
            'base_uri' => 'https://www.flickr.com/services/rest',
            'query' => [
                'api_key' => $this->apiKeyProvider->getFirst(),
                'format' => 'json',
                'nojsoncallback' => '1',
            ],
            'headers' => $this->uaProvider->getCommonApiClientHeaders()
        ];

        return $this->createClientWithProxy($opts, $this->flickrApiProxy);
    }

    public function createDownloadHttpClient(bool $randomIdentity = false): HttpClientInterface
    {
        return $this->createClientWithProxy(
            [
                'headers' => $randomIdentity
                    ? $this->uaProvider->getRandomBrowserHeaders()
                    : $this->uaProvider->getCommonBrowserHeaders(),
            ],
            $this->flickrDownloadProxy
        );
    }

    private function createClientWithProxy(array $opts, ?string $proxy): HttpClientInterface
    {
        if ($proxy !== null && $proxy !== '') {
            $opts['proxy'] = $proxy;
        }

        $client = HttpClient::create($opts);
        $this->verifyProxySupported($client, $opts);

        return $client;
    }

    /**
     * Ensures that proxy type requested can actually be used with a given backend
     *
     * @deprecated TODO move this function to some compiler pass to verify this ONCE
     *
     * @param HttpClientInterface $httpClient
     * @param array               $options
     *
     * @return void
     */
    private function verifyProxySupported(HttpClientInterface $httpClient, array $options): void
    {
        //No proxy was set, proxy is empty or it has no protocol (standard IP:PORT HTTP one supported by everything)
        if (!isset($options['proxy']) || $options['proxy'] === null || !\str_contains($options['proxy'], '://')) {
            return;
        }

        //HttpClient is a curl -> it supports all proxy types
        if ($httpClient instanceof CurlHttpClient) {
            return;
        }

        //All backends support http(s)://IP:PORT proxy
        if (\str_starts_with($options['proxy'], 'http:') || \str_starts_with($options['proxy'], 'https:')) {
            return;
        }

        throw new RuntimeException(
            \sprintf(
                'HTTP client is configured to use proxy "%s". However, the currently available backend of %s ' .
                'is not a derivative of %s. Non-cURL backends only support HTTP(S) proxies. To use %s:// proxy ' .
                'install cURL extension.',
                $options['proxy'],
                $httpClient::class,
                CurlHttpClient::class,
                \explode(':', $options['proxy'], 2)[0]
            )
        );
    }
}
