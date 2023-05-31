<?php
declare(strict_types=1);

namespace App\Struct;

use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly final class HttpClientConfig
{
    public function __construct(
        public AgentIdentity $agent,
        public ?string $proxy,
        public bool $isolateRequests = false
    ) {
    }

    public function asOptions(): array
    {
        $options = [];

        if ($this->proxy !== null && $this->proxy !== '') {
            $options['proxy'] = $this->proxy;

            //HTTP/2 multiplexing causes issues with anonymizing proxies in particular, but also corporate firewalls.
            //Due to a Symfony bug it's not possible to disable multiplexing without disabling HTTP/2 all-together
            // see https://github.com/symfony/symfony/issues/50488
            $options['http_version'] = '1.1';
        }

        //Multiplexing and connections reuse are a direct contradiction of isolating requests
        if ($this->isolateRequests) {
            $options['http_version'] = '1.1'; //see above
            $options['extra']['curl'][CURLOPT_FORBID_REUSE] = true;
            $options['extra']['curl'][CURLOPT_FRESH_CONNECT] = true;
        }


        $options['headers'] = $this->agent->headers;
        $options['headers']['User-Agent'] = $this->agent->userAgent;

        return $options;
    }
}
