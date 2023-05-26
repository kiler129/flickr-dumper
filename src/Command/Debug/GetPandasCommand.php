<?php
declare(strict_types=1);

namespace App\Command\Debug;

use App\Command\IdentitySwitching;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Factory\ApiClientConfigFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:get-pandas',
    description: 'List current Flickr Pandas. Exists mostly for API testing ;)',
)]
class GetPandasCommand extends Command
{
    use IdentitySwitching;

    private SymfonyStyle $io;

    public function __construct(private FlickrApiClient $apiClient, private ApiClientConfigFactory $apiConfigFactory) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addSwitchIdentitiesOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resolveSwitchIdentities($input);
        $this->io = new SymfonyStyle($input, $output);

        if ($this->switchIdentities) {
            $this->io->note('Identity switching enabled - generating random');
            $this->apiClient = $this->apiClient->withConfiguration($this->apiConfigFactory->getWithRandomClient());
        }

        $this->io->info('Please wait for Pandas arrival...');
        $pandas = $this->apiClient->getPanda()->getList()->getContent()['panda'];

        $this->io->title('The following Flickr Pandas are known:');
        $this->io->listing(\array_column($pandas, '_content'));

        $this->io->info('Your API is configured properly if you can see a list above :)');

        return Command::SUCCESS;
    }
}
