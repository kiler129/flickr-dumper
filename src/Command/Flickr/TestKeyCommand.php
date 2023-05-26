<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Command\IdentitySwitching;
use App\Exception\Api\ApiCallException;
use App\Flickr\Client\ApiClientConfig;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Struct\ApiError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'flickr:test-key',
    description: 'Verifies Flickr API key',
)]
class TestKeyCommand extends Command
{
    use IdentitySwitching;

    private SymfonyStyle $io;

    public function __construct(private FlickrApiClient $apiClient, private ApiClientConfigFactory $apiConfigFactory) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addSwitchIdentitiesOption($this);
        $this->addArgument(
            'key',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Key(s) to test; if not provided all in config are tested'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resolveSwitchIdentities($input);
        $this->io = new SymfonyStyle($input, $output);

        $keys = $input->getArgument('key');
        if (\count($keys) === 0) {
            $configs = \iterator_to_array($this->apiConfigFactory->getWithAllKeys($this->switchIdentities), false);
        } else {
            $configs = [];
            foreach ($keys as $key) {
                $configs[] = $this->switchIdentities ? $this->apiConfigFactory->getWithRandomClient($key)
                                                     : $this->apiConfigFactory->getWithCommonClient($key);
            }
        }

        $valid = $this->testConfigs($configs);
        $this->io->success(\sprintf('Testing finished - %d of %d keys are valid', $valid, \count($configs)));

        return Command::SUCCESS;
    }

    /**
     * @param array<ApiClientConfig> $configs
     */
    private function testConfigs(array $configs): int
    {
        $ct = \count($configs);
        $this->io->title(sprintf('Testing %d API keys', $ct));

        $n = 0;
        $valid = 0;
        $seen = [];
        foreach ($configs as $config) {
            $this->io->write(\sprintf('  %d of %d: ', (++$n), $ct));

            if (isset($seen[$config->apiKey])) {
                $this->io->writeln('=> ⚠️ Duplicate key');
                continue;
            }

            $seen[$config->apiKey] = true;
            $valid += $this->testApiConfig($config);
        }

        return $valid;
    }

    private function testApiConfig(ApiClientConfig $config): bool
    {
        $this->io->write(\sprintf('key "%s"... ', $config->apiKey));
        $apiClient = $this->apiClient->withConfiguration($config);

        try {
            $apiClient->getTest()->echo()->getContent(); //this will throw on API error
            $this->io->writeln('=> ✅ Valid');

            return true;

        } catch (ApiCallException $e) {
            if ($e->getCode() === ApiError::INVALID_API_KEY->value) {
                $this->io->writeln('=> ❌ Invalid');
            } else {
                $this->io->writeln('=> 🛑 API failed with error ' . $e->getCode());
            }

            return false;
        }
    }
}
