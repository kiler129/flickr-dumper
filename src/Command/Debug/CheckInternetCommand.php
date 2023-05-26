<?php
declare(strict_types=1);

namespace App\Command\Debug;

use App\Factory\HttpClientConfigFactory;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Factory\ApiClientConfigFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:debug:check-internet',
    description: 'Verifies internet connection',
)]
/**
 * @phpstan-type  TGetContent callable(string $url): ?string
 */
final class CheckInternetCommand extends Command
{
    private const HTTP_IP6 = 'https://ipv6.wtfismyip.com/json';
    private const HTTP_IP4 = 'https://ipv4.wtfismyip.com/json';
    private const HTTP_HEADERS = 'https://wtfismyip.com/headers';

    private SymfonyStyle $io;

    public function __construct(
        private HttpClientInterface $httpClient,
        private HttpClientConfigFactory $httpConfigFactory,
        private FlickrApiClient $apiClient,
        private ApiClientConfigFactory $apiConfigFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->testClient('default HTTP client', fn(string $url) => $this->getContentViaHttpClient($url, []));
        $this->testClient(
            'default HTTP w/random identity',
            fn(string $url) =>
                $this->getContentViaHttpClient($url, $this->httpConfigFactory->getWithRandomBrowser()->asOptions())
        );

        $this->testClient(
            'default API client',
            fn(string $url) => $this->getContentViaApiClient($url, $this->apiClient)
        );
        $this->testClient(
            'API client w/random identity',
            fn(string $url) => $this->getContentViaApiClient(
                $url,
                $this->apiClient->withConfiguration($this->apiConfigFactory->getWithRandomClient())
            )
        );

        return Command::SUCCESS;
    }

    private function getContentViaHttpClient(string $endpoint, array $httpOpts): ?string
    {
        try {
            $rsp = $this->httpClient->request('GET', $endpoint, $httpOpts);
            return $rsp->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->io->error(
                \sprintf('HTTP request to %s failed due to %s: %s', $endpoint, $e::class, $e->getMessage())
            );

            return null;
        }
    }

    private function getContentViaApiClient(string $endpoint, FlickrApiClient $apiClient): ?string
    {
        try {
            $rsp = $apiClient->rawHttpCall('GET', $endpoint, [], false);
            return $rsp->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->io->error(
                \sprintf('API request to %s failed due to %s: %s', $endpoint, $e::class, $e->getMessage())
            );

            return null;
        }
    }


    /**
     * @param TGetContent $getContent
     */
    private function testClient(string $clientName, callable $getContent): void
    {
        $this->io->title(\sprintf('Connection via %s client', $clientName));
        $this->testIpAddresses($getContent);
        $this->testHeaders($getContent);
        $this->io->text(\str_repeat('*', 120));
    }

    /**
     * @param TGetContent $getContent
     */
    private function testIpAddresses(callable $getContent): void
    {
        $this->testIpAddress('IPv4', self::HTTP_IP4, $getContent);
        $this->testIpAddress('IPv6', self::HTTP_IP6, $getContent);
    }

    /**
     * @param TGetContent $getContent
     */
    private function testIpAddress(string $type, string $endpoint, callable $getContent): void
    {
        $this->io->section($type . ' connectivity');

        $ip = $getContent($endpoint);
        if ($ip === null) {
            return;
        }

        $ipp = \json_decode($ip, true, 512, \JSON_THROW_ON_ERROR);
        $hostname = ($ipp['YourFuckingHostname'] === null ||
                    $ipp['YourFuckingHostname'] === $ipp['YourFuckingIPAddress'])
                    ? 'no hostname'
                    : $ipp['YourFuckingHostname'];

        $this->io->listing([
            \sprintf('Address: %s (%s)', $ipp['YourFuckingIPAddress'], $hostname),
            \sprintf('ISP: %s', $ipp['YourFuckingISP'] ?? 'Unknown'),
            \sprintf('Location: %s', $ipp['YourFuckingLocation'] ?? 'Unknown'),
            \sprintf('Tor detected: %s', $ipp['YourFuckingTorExit'] ? 'Yes' : 'No'),
        ]);
    }

    /**
     * @param TGetContent $getContent
     */
    private function testHeaders(callable $getContent): void
    {
        $headers = $getContent(self::HTTP_HEADERS);
        if ($headers === null) {
            return;
        }

        $headersSplit = [];
        foreach (\explode("\n", $headers) as $line) {
            $line = \trim($line);
            if ($line !== '') {
                $headersSplit[] = $line;
            }
        }

        $this->io->section('Request headers');
        $this->io->listing($headersSplit);
    }
}
