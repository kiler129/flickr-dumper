<?php
declare(strict_types=1);

namespace App\Flickr;

use App\Exception\Api\ApiCallException;
use App\Exception\Api\DecodeException;
use App\Exception\Api\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BaseApiClient
{
    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $flickrRestClient)
    {
        $this->http = $flickrRestClient;
    }

    public function callMethod(string $method, array $params = []): array
    {
        if (\strpos($method, 'flickr.') !== 0) {
            throw new \BadMethodCallException(\sprintf('Invalid method name "%s"', $method));
        }

        if (isset($params['method'])) {
            throw new \BadMethodCallException('Parameters array should not contain method');
        }

        $params['method'] = $method;

        $req = $this->http->request(
            'GET',
            '', //Flickr API uses query params for everything
            [
                'query' => $params,
            ]
        );

        try {
            $rawContent = $req->getContent();
        } catch (\Throwable $t) {
            throw TransportException::create('HTTP call to API failed without response', $t);
        }

        try {
            $decodedContent = \json_decode($rawContent, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw DecodeException::create('Failed to decode API response as JSON', $e, $rawContent);
        }

        if (!isset($decodedContent['stat'])) {
            throw ApiCallException::createFromCode(
                ApiCallException::UNEXPECTED_RESPONSE,
                '"stat" field is missing, cannot determine response state (failure or success)',
                null,
                $rawContent
            );
        }

        if ($decodedContent['stat'] === 'ok') {
            return $decodedContent;
        }

        if (!isset($decodedContent['code'])) {
            throw ApiCallException::createFromCode(
                ApiCallException::UNKNOWN_ERROR,
                'API responded with failure without providing reason code',
                null,
                $rawContent
            );
        }

        if ((string)(int)$decodedContent['code'] !== (string)$decodedContent['code']) {
            throw ApiCallException::createFromCode(
                ApiCallException::UNEXPECTED_RESPONSE,
                \sprintf(
                    'API responded with failure with non-integer error code (%s)',
                    (string)$decodedContent['code']
                ),
                null,
                $rawContent
            );
        }

        throw ApiCallException::createFromCode(
            (int)$decodedContent['code'],
            $decodedContent['message'] ?? null,
            null,
            $rawContent
        );
    }
}
