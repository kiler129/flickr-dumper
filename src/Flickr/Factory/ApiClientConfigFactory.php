<?php
declare(strict_types=1);

namespace App\Flickr\Factory;

use App\Exception\InvalidArgumentException;
use App\Factory\HttpClientConfigFactory;
use App\Flickr\Client\ApiClientConfig;

final class ApiClientConfigFactory
{
    private ?array $keys;
    private readonly int $keyLast;

    public function __construct(
        ?array $apiKeys,
        private HttpClientConfigFactory $httpConfigFactory)
    {
        $this->keys = $apiKeys;
        $this->keyLast = $apiKeys === null ? -1 : \count($apiKeys)-1; //null or empty array handling
        if ($this->keyLast === -1) {
            throw new InvalidArgumentException('You need to specify at least one API key');
        }

        \shuffle($apiKeys); //Ensure we're not hitting the first one disproportionally
    }

    public function getWithCommonClient(?string $customKey = null): ApiClientConfig
    {
        return new ApiClientConfig(
            $customKey ?? $this->getRandomKey(),
            $this->httpConfigFactory->getWithCommonCliClient()
        );
    }

    public function getWithRandomClient(?string $customKey = null): ApiClientConfig
    {
        return new ApiClientConfig(
            $customKey ?? $this->getRandomKey(),
            $this->httpConfigFactory->getWithRandomCliClient()
        );
    }

    /**
     * @return iterable<string, ApiClientConfig>
     */
    public function getWithAllKeys(bool $randomHttpClient = true): iterable
    {
        foreach ($this->keys as $key)
        {
            yield $key => new ApiClientConfig(
                $key,
                $randomHttpClient
                    ? $this->httpConfigFactory->getWithRandomCliClient()
                    : $this->httpConfigFactory->getWithCommonCliClient()
            );
        }
    }

    private function getRandomKey(): ?string
    {
        //use mt_random to be fast as this isn't crypto
        return $this->keyLast === 0 ? $this->keys[0] : $this->keys[\mt_rand(0, $this->keyLast)];
    }
}
