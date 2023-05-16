<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\ApiCallException;
use App\Exception\Api\DecodeException;
use App\Exception\Api\TransportException;
use App\Exception\Api\UnexpectedResponseException;
use App\Exception\LogicException;
use App\Struct\ApiError;
use App\Util\AgentIdentityProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @deprecated
 */
final class BaseApiClient
{
    private array $overrideHeaders;
    private string $overrideApiKey;

    public function __construct(
        private HttpClientInterface $flickrApiHttpClient,
        private ApiKeyProvider $apiKeyProvider,
        private AgentIdentityProvider $uaProvider,
    ) {
    }

    public function callMethod(string $method, array $params = []): array
    {
        $req = $this->flickrApiHttpClient->request(
            'GET',
            '', //Flickr API uses query params for everything
            $this->buildHttpClientOptions($method, $params)
        );


        try {
            $rawContent = $req->getContent();
        } catch (\Throwable $t) {
            throw TransportException::create('HTTP call to API failed without response', $t);
        }

        // \/ Debug identity changing
        $x = $req->getInfo('debug');
        preg_match_all('/^user-agent: (.*)/im', $x, $ua);
        preg_match_all('/api_key=([a-f0-9]{32})/im', $x, $key);
        $ident = \sprintf('IDENT for %s: UA<%s> AK<%s>', $method, $ua[1][0] ?? 'UNK', $key[1][0] ?? 'UNK');
        dump($ident);

        try {
            $decodedContent = \json_decode($rawContent, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw DecodeException::create('Failed to decode API response as JSON', $e, $rawContent);
        }

        if (!isset($decodedContent['stat'])) {
            throw UnexpectedResponseException::create(
                '"stat" field is missing, cannot determine response state (failure or success)',
                $decodedContent
            );
        }

        if ($decodedContent['stat'] === 'ok') {
            return $decodedContent;
        }

        if (!isset($decodedContent['code'])) {
            throw UnexpectedResponseException::createFromError(
                ApiError::UNKNOWN_ERROR,
                'API responded with failure without providing reason code',
                null,
                $decodedContent
            );
        }

        if ((string)(int)$decodedContent['code'] !== (string)$decodedContent['code']) {
            throw UnexpectedResponseException::create(
                \sprintf(
                    'API responded with failure with non-integer error code (%s)',
                    (string)$decodedContent['code']
                ),
                $decodedContent
            );
        }

        throw ApiCallException::createFromError(
            (int)$decodedContent['code'],
            $decodedContent['message'] ?? null,
            null,
            $rawContent
        );
    }

    public function fetchResult(string $method, string $collectionName, array $params = []): array
    {
        $rsp = $this->callMethod($method, $params);
        if (!isset($rsp[$collectionName])) {
            throw UnexpectedResponseException::create('API did not return ' . $collectionName, $rsp);
        }

        return $rsp[$collectionName];
    }

    public function randomizeHttpIdentity(): void
    {
        $this->overrideHeaders = $this->uaProvider->getRandomApiClientHeaders();
    }

    public function randomizeApiIdentity(): void
    {
        $this->overrideApiKey = $this->apiKeyProvider->getRandom();
    }

    public function randomizeIdentity(): void
    {
        $this->randomizeHttpIdentity();
        $this->randomizeApiIdentity();
    }

    public function setApiKey(string $key): void
    {
        $this->overrideApiKey = $key;
    }

    public function resetIdentity(): void
    {
        if (!isset($this->overrideHeaders) && !isset($this->overrideApiKey)) {
            //This prevents dumb assumption that resetIdentity == make a random one
            throw new LogicException(
                'Cannot reset identity - it is already a default one. Did you mean to call randomizeIdentity()?'
            );
        }

        unset($this->overrideHeaders, $this->overrideApiKey);
    }

    private function buildHttpClientOptions(string $apiMethod, array $userParams): array
    {
        $options = [];

        if (!\str_starts_with($apiMethod, 'flickr.')) {
            throw new \BadMethodCallException(\sprintf('Invalid method name "%s"', $apiMethod));
        }

        if (isset($params['method'])) {
            throw new \BadMethodCallException(
                'Parameters array should not contain method (it should be passed as an argument)'
            );
        }

        $options['query'] = $userParams;
        $options['query']['method'] = $apiMethod;


        if (isset($this->overrideHeaders)) {
            $options['headers'] = $this->overrideHeaders;
        }

        if (isset($this->overrideApiKey)) {
            $options['query']['api_key'] = $this->overrideApiKey;
        }

        return $options;
    }
}
