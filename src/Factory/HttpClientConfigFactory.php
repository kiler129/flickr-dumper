<?php
declare(strict_types=1);

namespace App\Factory;

use App\Struct\HttpClientConfig;
use App\Util\AgentIdentityProvider;

final class HttpClientConfigFactory
{
    private ?array $proxies;
    private readonly int $proxyLast;

    public function __construct(
        ?array $proxies,
        private AgentIdentityProvider $agentProvider,
    ) {
        $this->proxies = $proxies;

        if ($proxies === null) {
            $this->proxyLast = -1;
        } else {
            \shuffle($proxies); //Ensure we're not hitting the first one disproportionally
            $this->proxyLast = \count($proxies) - 1;
        }
    }

    public function getWithCommonBrowser(): HttpClientConfig
    {
        return new HttpClientConfig($this->agentProvider->getCommonBrowser(), $this->getRandomProxy());
    }

    public function getWithCommonCliClient(): HttpClientConfig
    {
        return new HttpClientConfig($this->agentProvider->getCommonCliClient(), $this->getRandomProxy());
    }

    public function getWithRandomBrowser(): HttpClientConfig
    {
        return new HttpClientConfig($this->agentProvider->getRandomBrowser(), $this->getRandomProxy(), true);
    }

    public function getWithRandomCliClient(): HttpClientConfig
    {
        return new HttpClientConfig($this->agentProvider->getRandomCliClient(), $this->getRandomProxy(), true);
    }

    private function getRandomProxy(): ?string
    {
        return match ($this->proxyLast) {
            -1 => null,
            0 => $this->proxies[0],
            default => $this->proxies[\mt_rand(0, $this->proxyLast)] //use mt_random to be fast as this isn't crypto
        };
    }
}
