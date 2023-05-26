<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Command\BaseDownloadCommand;
use App\Command\IdentitySwitching;
use App\Factory\HttpClientFactory;
use App\Flickr\BaseApiClient;
use App\Flickr\Client\FlickrApiClient;
use App\Flickr\Enum\MediaCollectionType;
use App\Flickr\Factory\ApiClientConfigFactory;
use App\Flickr\PhotoSets;
use App\Flickr\Struct\PhotosetDto;
use App\Flickr\Urls;
use App\UseCase\ResolveOwner;
use App\Util\NameGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'flickr:sync-user-photosets',
    aliases: ['flickr:sync-user-albums'],
    description: 'Downloads/syncs all user photosets (aka. albums); it\'s a mass version of flickr:sync-collection'
)]
class SyncUserPhotosetsCommand extends Command
{
    use IdentitySwitching;

    private Command      $syncPhotosetsCmd;
    private SymfonyStyle $io;

    public function __construct(
        private LoggerInterface $log,
        private ResolveOwner $resolveOwner,
        private FlickrApiClient $api,
        private ApiClientConfigFactory $apiConfigFactory
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this

            /********* Beginning of common options passed as-is to SyncCollectionCommand *********/
            ->addOption(
                'ignore-completed',
                null,
                InputOption::VALUE_NONE,
                'Do not attempt to sync collections that were synced at least once. This option takes priority over --distrust-timestamps.'
             )
             ->addOption(
                 'distrust-timestamps',
                 null,
                 InputOption::VALUE_NONE,
                 'Changes in collections rely on Flickr update timestamps. This options verifies items lists manually.'
             )
             ->addOption(
                 'repair-files',
                 null,
                 InputOption::VALUE_NONE,
                 'Do not trust the database<=>fs consistency (in case you mangled files manually)'
             )
             ->addOption(
                 'index-only',
                 'i',
                 InputOption::VALUE_NONE,
                 'Do not download any files'
             )
             /********* End of common options passed as-is to SyncCollectionCommand *********/

             ->addArgument('user', InputArgument::REQUIRED, 'User screenname (e.g. spacex) or NSID');

        $this->addSwitchIdentitiesOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resolveSwitchIdentities($input);
        $this->io = new SymfonyStyle($input, $output);

        $user = $input->getArgument('user');
        $userIdentity = $this->resolveOwner->lookupUserIdentityByPathAlias($user);

        if (!$userIdentity) {
            $this->io->error(\sprintf('User "%s" does\'t seem to exist', $user));
            return Command::FAILURE;
        }


        $this->ensureIdentity();
        $this->log->info('Loading list of albums for NSID=' . $userIdentity->nsid);

        //Pre-generate common options once as these will not change for each album
        $commonOptions = $this->getCommonOptions($input, $output);
        $success = $fail = 0;
        foreach ($this->api->getPhotosets()->getListIterable($userIdentity->nsid) as $apiPhotoset)
        {
            $photoset = PhotosetDto::fromGenericApiResponse($apiPhotoset);

            if ($this->syncPhotoset($photoset, $commonOptions, $output)) {
                $this->io->success(
                    \sprintf('Photoset "%s" (id=%d) synced successfully', $photoset->title, $photoset->id)
                );
                ++$success;
            } else {
                $this->io->error(
                    \sprintf('Photoset "%s" (id=%d) synced failed - see output above', $photoset->title, $photoset->id)
                );
                ++$fail;
            }
        }

        if ($fail === 0) {
            $this->io->success(\sprintf('Successfully processed all %d albums', $success));

            return Command::SUCCESS;
        }

        $this->io->warning(\sprintf('Successfully processed all %d albums; %d failed', $success, $fail));

        return Command::FAILURE;
    }

    private function syncPhotoset(PhotosetDto $photoset, array $options, OutputInterface $output): bool
    {
        $this->syncPhotosetsCmd ??= $this->getApplication()
                                         ->find(\explode('|', SyncCollectionCommand::getDefaultName(), 2)[0]);

        $options['--user-id'] = $photoset->ownerNsid;
        $options['--type'] = MediaCollectionType::ALBUM->value;



        $options['collection'] = $photoset->id;

        try {
            return $this->syncPhotosetsCmd->run(new ArrayInput($options), $output) === Command::SUCCESS;
        } catch (\Throwable $t) {
            $this->io->error(
                \sprintf(
                    'The photoset sync command crashed due to %s: %s',
                    $t::class,
                    $t->getMessage()
                )
            );

            dump($t);

            return false;
        }
    }

    public function getCommonOptions(InputInterface $input, OutputInterface $output): array
    {
        $commonOptions = [
            '--ignore-completed' => $input->getOption('ignore-completed'),
            '--distrust-timestamps' => $input->getOption('distrust-timestamps'),
            '--repair-files' => $input->getOption('repair-files'),
            '--index-only' => $input->getOption('index-only')
        ];

        if ($output->isVerbose()) {
            $commonOptions['-v'] = true;
        } elseif ($output->isVeryVerbose()) {
            $commonOptions['-vv'] = true;
        } elseif ($output->isDebug()) {
            $commonOptions['-vvv'] = true;
        } elseif ($output->isQuiet()) {
            $commonOptions['-q'] = true;
        }

        return $commonOptions;
    }

    private function ensureIdentity(): void
    {
        if (!$this->switchIdentities) {
            return;
        }

        $this->log->info('Switching API identity during user album listing');
        $this->api = $this->api->withConfiguration($this->apiConfigFactory->getWithRandomClient());
    }
}
