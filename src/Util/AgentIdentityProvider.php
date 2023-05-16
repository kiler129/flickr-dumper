<?php
declare(strict_types=1);

namespace App\Util;

use App\Struct\AgentIdentity;

class AgentIdentityProvider
{
    /** @deprecated */
    private const COMMON_BROWSER_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
    ];

    /** @deprecated */
    private const COMMON_API_CLIENT_HEADERS = [
        'User-Agent' => 'curl/7.82.0',
        'Accept: */*',
    ];

    private const COMMON_BROWSER = [
        'ua' => 'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0',
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
        ]
    ];

    private const COMMON_CLI_CLIENT = [
        'ua' => 'curl/7.82.0',
        'headers' => [
            'Accept' => '*/*'
        ]
    ];

    private const BROWSERS = [
        'chrome' => [
            'uas' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
                'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
                'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_3_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/113.0.5672.109 Mobile/15E148 Safari/604.1',
                'Mozilla/5.0 (iPad; CPU OS 16_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/113.0.5672.109 Mobile/15E148 Safari/604.1',
                'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; SM-A205U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; SM-A102U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; SM-N960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; LM-Q720) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; LM-X420) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 10; LM-Q710(FGN)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.5672.76 Mobile Safari/537.36',
            ],
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'language' => 'en-US,en;q=0.7',
            'headers' => [
                'Sec-Fetch-User: ?1',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Gpc: 1',
                'Sec-Fetch-Site: same-origin',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Dest: document',
                'Upgrade-Insecure-Requests: 1',
            ],
        ],
        'firefox' => [
            'uas' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 13.3; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (X11; Linux i686; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/113.0',
                'Mozilla/5.0 (Android 13; Mobile; rv:109.0) Gecko/113.0 Firefox/113.0',
                'Mozilla/5.0 (iPad; CPU OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/113.0 Mobile/15E148 Safari/605.1.15',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/113.0 Mobile/15E148 Safari/605.1.15',
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0',
            ],
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'language' => 'en-US,en;q=0.5',
            'headers' => [
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
            ],
        ],
    ];

    private const CLI_CLIENTS = [
        'curl' => [
            '8.0.1',
            '8.0.0',
            '7.88.1',
            '7.88.0',
            '7.87.0',
            '7.86.0',
            '7.85.0',
            '7.84.0',
            '7.83.1',
            '7.83.0',
            '7.82.0',
            '7.81.0',
            '7.80.0',
            '7.79.1',
            '7.79.0',
            '7.78.0',
            '7.77.0',
            '7.76.1',
            '7.76.0',
            '7.75.0',
            '7.74.0',
            '7.73.0',
            '7.72.0',
            '7.71.1',
            '7.71.0',
            '7.70.0',
            '7.69.1',
            '7.69.0',
            '7.68.0',
            '7.67.0',
            '7.66.0',
            '7.65.3',
            '7.65.2',
            '7.65.1',
            '7.65.0',
            '7.64.1',
            '7.64.0',
            '7.63.0',
            '7.62.0',
            '7.61.1',
            '7.61.0',
            '7.60.0',
            '7.59.0',
            '7.58.0',
            '7.57.0',
            '7.56.1',
            '7.56.0',
            '7.55.1',
            '7.55.0',
            '7.54.1',
            '7.54.0',
            '7.53.1',
            '7.53.0',
            '7.52.1',
            '7.52.0',
            '7.51.0',
            '7.50.3',
            '7.50.2',
            '7.50.1',
            '7.50.0',
            '7.49.1',
            '7.49.0',
            '7.48.0',
            '7.47.1',
            '7.47.0',
            '7.46.0',
            '7.45.0',
            '7.44.0',
            '7.43.0',
            '7.42.1',
            '7.42.0',
            '7.41.0',
            '7.40.0',
            '7.39.0',
            '7.38.0',
            '7.37.1',
            '7.37.0',
            '7.36.0',
            '7.35.0',
            '7.34.0',
            '7.33.0',
            '7.32.0',
            '7.31.0',
            '7.30.0',
            '7.29.0',
            '7.28.1',
            '7.28.0',
            '7.27.0',
            '7.26.0',
            '7.25.0',
            '7.24.0',
            '7.23.1',
            '7.23.0',
            '7.22.0',
            '7.21.7',
            '7.21.6',
            '7.21.5',
            '7.21.4',
            '7.21.3',
            '7.21.2',
            '7.21.1',
            '7.21.0',
            '7.20.1',
            '7.20.0',
            '7.19.7',
            '7.19.6',
            '7.19.5',
            '7.19.4',
            '7.19.3',
            '7.19.2',
            '7.19.1',
            '7.19.0',
            '7.18.2',
            '7.18.1',
            '7.18.0',
            '7.17.1',
            '7.17.0',
            '7.16.4',
            '7.16.3',
            '7.16.2',
            '7.16.1',
            '7.16.0',
            '7.15.5',
            '7.15.4',
            '7.15.3',
            '7.15.2',
            '7.15.1',
            '7.15.0',
            '7.14.1',
            '7.14.0',
            '7.13.2',
            '7.13.1',
            '7.13.0',
            '7.12.3',
            '7.12.2',
            '7.12.1',
            '7.12.0',
            '7.11.2',
            '7.11.1',
            '7.11.0',
            '7.10.8',
            '7.10.7',
            '7.10.6',
            '7.10.5',
            '7.10.4',
            '7.10.3',
            '7.10.2',
            '7.10.1',
            '7.10',
            '7.9.8',
            '7.9.7',
            '7.9.6',
            '7.9.5',
            '7.9.4',
            '7.9.3',
            '7.9.2',
            '7.9.1',
            '7.9',
            '7.8.1',
            '7.8',
            '7.7.3',
            '7.7.2',
            '7.7.1',
            '7.7',
            '7.6.1',
            '7.6',
        ],
        'Wget' => [
            '1.13.1',
            '1.13.3',
            '1.13.4',
            '1.13',
            '1.14',
            '1.15',
            '1.16.1',
            '1.16.2',
            '1.16.3',
            '1.16',
            '1.17.1',
            '1.17',
            '1.18',
            '1.19.1',
            '1.19.2',
            '1.19.3',
            '1.19.4',
            '1.19.5',
            '1.19',
            '1.20.1',
            '1.20.2',
            '1.20.3',
            '1.20',
            '1.21.1',
            '1.21.2',
            '1.21.3',
            '1.21.4',
            '1.21',
            '1.99.2',
            '2.0.0',
            '2.0.1',
        ],
        'PostmanRuntime' => [
            '7.0.1',
            '7.1.0',
            '7.1.1',
            '7.1.2',
            '7.1.3',
            '7.1.4',
            '7.1.5',
            '7.1.6',
            '7.10.0',
            '7.11.0',
            '7.12.0',
            '7.13.0',
            '7.14.0',
            '7.15.0',
            '7.15.1',
            '7.15.2',
            '7.16.0',
            '7.16.1',
            '7.16.2',
            '7.16.3',
            '7.17.0',
            '7.17.1',
            '7.18.0',
            '7.19.0',
            '7.2.0',
            '7.20.0',
            '7.20.1',
            '7.21.0',
            '7.22.0',
            '7.23.0',
            '7.24.0',
            '7.24.1',
            '7.24.2',
            '7.25.0',
            '7.26.0',
            '7.26.1',
            '7.26.10',
            '7.26.2',
            '7.26.3',
            '7.26.4',
            '7.26.5',
            '7.26.6',
            '7.26.7',
            '7.26.8',
            '7.26.9',
            '7.27.0',
            '7.28.0',
            '7.28.1',
            '7.28.2',
            '7.28.3',
            '7.28.4',
            '7.29.0',
            '7.29.1',
            '7.29.2',
            '7.29.3',
            '7.3.0',
            '7.30.0',
            '7.30.1',
            '7.31.0',
            '7.31.1',
            '7.31.3',
            '7.32.0',
            '7.32.1',
            '7.32.2',
            '7.4.0',
            '7.4.1',
            '7.4.2',
            '7.5.0',
            '7.6.0',
            '7.6.1',
            '7.7.0',
            '7.7.1',
            '7.8.0',
            '7.9.0',
            '7.9.1',
        ],
    ];

    public function getCommonBrowser(): AgentIdentity
    {
        return new AgentIdentity(self::COMMON_BROWSER['ua'], self::COMMON_BROWSER['headers']);
    }

    public function getRandomBrowser(): AgentIdentity
    {
        $kind = \array_keys(self::BROWSERS)[\random_int(0, \count(self::BROWSERS)-1)]; //e.g. chrome, firefox, etc...
        $data = self::BROWSERS[$kind]; //dataset to pick from to get a given kind of browser profile

        $headers = $data['headers'];
        unset($headers[\random_int(0, \count($headers)-1)]); //more entropy by removing some not important header

        //Ensure all important headers are set thou, so CDN or WAF doesn't block us in the future
        $headers['Accept'] = $data['accept'];
        $headers['Accept-Language'] = $data['language'];

        return new AgentIdentity(
            $data['uas'][\random_int(0, \count($data['uas'])-1)],
            $headers
        );
    }

    public function getCommonCliClient(): AgentIdentity
    {
        return new AgentIdentity(self::COMMON_CLI_CLIENT['ua'], self::COMMON_CLI_CLIENT['headers']);
    }


    public function getRandomCliClient(): AgentIdentity
    {
        $uaKind = \array_keys(self::CLI_CLIENTS)[\random_int(0, \count(self::CLI_CLIENTS)-1)];
        $uaVersion = self::CLI_CLIENTS[$uaKind][\random_int(0, \count(self::CLI_CLIENTS[$uaKind])-1)];

        return new AgentIdentity(\sprintf('%s/%s', $uaKind, $uaVersion), ['Accept' => '*/*']);
    }





    ////////////////////////////// DEPRECATED METHOD //////////////////////////////

    /** @deprecated exists only as polyfill here for now */
    private function identityToHeaders(AgentIdentity $id): array
    {
        $headers = $id->headers;
        $headers['User-Agent'] = $id->userAgent;

        return $headers;
    }

    /**
     * @deprecated use getCommonBrowser
     */
    public function getCommonBrowserHeaders(): array
    {
        return $this->identityToHeaders($this->getCommonBrowser());
    }

    /** @deprecated use getRandomBrowser()  */
    public function getRandomBrowserHeaders(): array
    {
        return $this->identityToHeaders($this->getRandomBrowser());
    }

    public function getCommonApiClientHeaders(): array
    {
        return $this->identityToHeaders($this->getCommonCliClient());
    }

    public function getRandomApiClientHeaders(): array
    {
        return $this->identityToHeaders($this->getRandomCliClient());
    }
}
