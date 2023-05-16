<?php
declare(strict_types=1);

namespace App\Command;

use App\Flickr\BaseApiClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @property HttpClientInterface|null $downloadHttpClient
 * @property BaseApiClient $apiClient
 * @method self addOption(...$opts)
 */
trait RandomizationAware
{
    private bool $randomizeHttpIdentity;
    private bool $randomizeApiIdentity;

    private function addRandomizationConfigOptions(): void
    {
        $this
            ->addOption('randomize-client', null, InputOption::VALUE_NONE, 'Randomizes HTTP client identity')
            ->addOption(
                'randomize-identity',
                'r',
                InputOption::VALUE_NONE,
                'Randomizes API keys & HTTP client identity (implies --randomize-client)'
            );
    }

    private function configureRandomizationFromOptions(InputInterface $input): void
    {
        $this->randomizeApiIdentity = (bool)$input->getOption('randomize-identity');
        if ($this->randomizeApiIdentity) { //in case something is checking both options
            $input->setOption('randomize-client', true);
        }

        $this->randomizeHttpIdentity = (bool)$input->getOption('randomize-client');

        //dump('Randomize API: ' . (int)$this->randomizeApiIdentity);
        //dump('Randomize HTTP: ' . (int)$this->randomizeHttpIdentity);

        //We want to do it once so that first request is never non-random if randomization was desired
        $this->ensureClientsIdentities();
    }

    protected function ensureDownloadClientIdentity(): void
    {
        if (!$this->randomizeHttpIdentity || !isset($this->downloadHttpClient)) {
            return;
        }

        $this->downloadHttpClient = $this->clientFactory->createDownloadHttpClient(true);
    }

    protected function ensureApiClientIdentity(): void
    {
        if ($this->randomizeHttpIdentity) {
            $this->apiClient->randomizeHttpIdentity();
        }

        if ($this->randomizeApiIdentity) {
            $this->apiClient->randomizeApiIdentity();
        }
    }

    protected function ensureClientsIdentities(): void
    {
        $this->ensureDownloadClientIdentity();
        $this->ensureApiClientIdentity();
    }
}
