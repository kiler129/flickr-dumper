<?php
declare(strict_types=1);

namespace App\Struct;

use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly final class HttpClientConfig
{
    private const DEFAULT_UA = 'FlickrSync/v1.0';

    public function __construct(
        public AgentIdentity $agent,
        public ?string $proxy,
    ) {
    }

    //public function getHash(): string
    //{
    //    return sha1((string)$this->proxy . '/' . $this->agent->userAgent);
    //}

    public function asOptions(): array
    {
        $options = [];

        if ($this->proxy !== null && $this->proxy !== '') {
            $options['proxy'] = $this->proxy;
        }

        $options['headers'] = $this->agent->headers;
        $options['headers']['User-Agent'] = $this->agent->userAgent;

        return $options;
    }
}
