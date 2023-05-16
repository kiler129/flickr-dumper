<?php
declare(strict_types=1);

namespace App\Command;

use App\Exception\Api\ApiCallException;
use App\Flickr\ApiKeyProvider;
use App\Flickr\BaseApiClient;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Panda;
use App\Flickr\Test;
use App\Flickr\Urls;
use App\Struct\ApiError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'flickr:test-key',
    description: 'Verifies Flickr API key',
)]
class TestKeyCommand extends Command
{
    use RandomizationAware;

    private SymfonyStyle $io;

    public function __construct(private FlickrApiClient $apiClient) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addRandomizationConfigOptions();

        $this->addArgument(
            'key',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Key(s) to test; if not provided all in config are tested'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->configureRandomizationFromOptions($input);
        $this->io = new SymfonyStyle($input, $output);

        $keys = $input->getArgument('key');
        if (\count($keys) === 0) {
            $keys = $this->keyProvider->getAll();
        }

        $valid = 0;
        $ct = \count($keys);
        $this->io->title(sprintf('Testing %d API keys', $ct));

        if (\count(\array_unique($keys)) !== $ct) {
            $this->io->warning('List of keys contains duplicated entries');
        }

        foreach ($keys as $n => $key) {
            $this->io->write(\sprintf('  %d of %d: ', $n+1, $ct));
            $valid += (int)$this->testApiKey($key);
        }

        $this->io->success(\sprintf('Testing finished - %d of %d keys are valid', $valid, $ct));

        return Command::SUCCESS;
    }

    private function testApiKey(string $key): bool
    {
        $this->io->write(\sprintf('key "%s"... ', $key));

        $result = false;
        $this->apiClient->setApiKey($key);

        try {
            $result = $this->testApi->echoTest();

            if ($result['stat'] ?? null === 'ok') {
                $this->io->writeln('=> ✅ Valid');
                $result = true;
            } else {
                $this->io->writeln('=> ⚠️ Non-error unexpected response');
            }

        } catch (ApiCallException $e) {
            if ($e->getCode() === ApiError::INVALID_API_KEY->value) {
                $this->io->writeln('=> ❌ Invalid');
            } else {
                $this->io->writeln('=> 🛑 API failed with error ' . $e->getCode());
            }
        }

        $this->ensureApiClientIdentity();

        return $result;
    }
}
