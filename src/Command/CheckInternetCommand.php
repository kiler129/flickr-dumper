<?php
declare(strict_types=1);

namespace App\Command;

use App\Flickr\Panda;
use App\Flickr\Urls;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'debug:check-internet',
    description: 'Verifies internet connection',
)]
final class CheckInternetCommand extends Command
{
    private const HTTP_IP6 = 'https://ipv6.wtfismyip.com/json';
    private const HTTP_IP4 = 'https://ipv4.wtfismyip.com/json';
    private const HTTP_HEADERS = 'https://wtfismyip.com/headers';

    private SymfonyStyle $io;

    public function __construct(
        private HttpClientInterface $bareHttpClient,
        private HttpClientInterface $flickrApiHttpClient,
        private HttpClientInterface $flickrDownloadHttpClient,
        private string $flickrApiProxy,
        private string $flickrDownloadProxy,

    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->testHttpClient('direct HTTP', $this->bareHttpClient, null);
        $this->testHttpClient('Flickr API', $this->flickrApiHttpClient, $this->flickrApiProxy ?: null);
        $this->testHttpClient('Flickr Download', $this->flickrDownloadHttpClient, $this->flickrDownloadProxy ?: null);

        return Command::SUCCESS;
    }

    private function testHttpClient(string $clientName, HttpClientInterface $client, ?string $proxy): void
    {
        $this->io->title(\sprintf('Connection via %s client', $clientName));
        $this->testIpAddresses($client, $proxy);
        $this->testHeaders($client);
        $this->io->text(\str_repeat('*', 80));
    }

    private function testIpAddresses(HttpClientInterface $client, ?string $proxy): void
    {
        $this->io->listing([
            'Proxy: ' . ($proxy ?? '*operating system default*'),
        ]);

        $this->testIpAddress('IPv4', $client, self::HTTP_IP4);
        $this->testIpAddress('IPv6', $client, self::HTTP_IP6);
    }

    private function testIpAddress(string $type, HttpClientInterface $client, string $endpoint): void
    {
        $this->io->section($type . ' connectivity');

        $ip = $this->getContent($client, $endpoint, null);
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

    private function testHeaders(HttpClientInterface $client): void
    {
        $headers = $this->getContent($client, self::HTTP_HEADERS);
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

    private function getContent(HttpClientInterface $client, string $endpoint): ?string
    {
        try {
            $rsp = $client->request('GET', $endpoint, ['query' => []]);
            return $rsp->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->io->error(
                \sprintf('Request to %s failed due to %s: %s', $endpoint, $e::class, $e->getMessage())
            );

            return null;
        }
    }
}
