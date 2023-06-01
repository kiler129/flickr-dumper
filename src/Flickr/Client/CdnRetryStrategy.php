<?php
declare(strict_types=1);

namespace App\Flickr\Client;

use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CdnRetryStrategy extends GenericRetryStrategy
{
    private const CDN_FETCH_METHODS = ['GET', 'HEAD'];

    public const DEFAULT_RETRY_STATUS_CODES = [
        0 => self::CDN_FETCH_METHODS, //transport exceptions
        404 => self::CDN_FETCH_METHODS, //CloudFront sometimes just 404s but then on subsequent request succeeds :shrug:
        423 => self::CDN_FETCH_METHODS,
        425 => self::CDN_FETCH_METHODS,
        429 => self::CDN_FETCH_METHODS,
        500 => self::CDN_FETCH_METHODS,
        502 => self::CDN_FETCH_METHODS,
        503 => self::CDN_FETCH_METHODS,
        504 => self::CDN_FETCH_METHODS,
        507 => self::CDN_FETCH_METHODS,
        510 => self::CDN_FETCH_METHODS,
    ];

    public function __construct(
        array $statusCodes = self::DEFAULT_RETRY_STATUS_CODES,
        int $delayMs = 1500,
        float $multiplier = 1.5,
        int $maxDelayMs = 10000,
        float $jitter = 0.3
    ) {
        parent::__construct($statusCodes, $delayMs, $multiplier, $maxDelayMs, $jitter);
    }

    public function shouldRetry(
        AsyncContext $context,
        ?string $responseContent,
        ?TransportExceptionInterface $exception
    ): ?bool {
        $generic = parent::shouldRetry($context, $responseContent, $exception);
        if ($generic === true) {
            return true;
        }

        //This sometimes happens just out of blue and a HTML error is returned with HTTP/200 code by CloudFront
        $headers = $context->getHeaders();
        if (isset($headers['content-type'][0]) && \str_starts_with($headers['content-type'][0], 'text/')) {
            return true;
        }

        return $generic;
    }

}
