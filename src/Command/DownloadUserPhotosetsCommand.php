<?php
declare(strict_types=1);

namespace App\Command;

use App\Factory\HttpClientFactory;
use App\Flickr\BaseApiClient;
use App\Flickr\PhotoSets;
use App\Flickr\Urls;
use App\Util\NameGenerator;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'flickr:download-user-photosets',
    aliases: ['flickr:download-user-albums'],
    description: 'Downloads all user photosets (aka. albums)'
)]
class DownloadUserPhotosetsCommand extends BaseDownloadCommand
{
    private Command $dlPhotosetCmd;

    public function __construct(
        HttpClientFactory $clientFactory,
        Filesystem $fs,
        BaseApiClient $apiClient,
        NameGenerator $nameGenerator,
        private Urls $flickrUrls,
        private PhotoSets $flickrAlbums,
    ) {
        parent::__construct($clientFactory, $fs, $apiClient, $nameGenerator);
    }

    protected function configure()
    {
        $this
             ->addOption(
                 'check-all',
                 'c',
                 InputOption::VALUE_NONE,
                 'By default the whole album is skipped if its directory exist, this option forces re-listing of ' .
                 'all albums (except blacklisted)'
             )
             ->addArgument('user', InputArgument::REQUIRED, 'URL to user resource (profile/photo/etc.) or NSID');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('This command is deprecated');
        die;

        $parentExit = parent::execute($input, $output);
        if ($parentExit !== Command::SUCCESS) {
            return $parentExit;
        }

        $user = $input->getArgument('user');
        $userIsUrl = (preg_match('/^https?:\/\//', $user) === 1);
        $nsid = $userIsUrl ? $this->flickrUrls->lookupUserId($user) : $user;

        $targetDir = $input->getArgument('destination');
        if (empty($targetDir)) {
            $targetDir = $this->getUserPhotosetsDir($nsid);
        }
        $this->fs->mkdir($targetDir);
        $forceCheckAllSets = (bool)$input->getOption('check-all');

        $existingSets = $this->findExisting($targetDir);
        $photosets = $this->flickrAlbums->iterateListFlat($nsid);

        $setsCounter = 0;
        $this->io->info('Getting albums...');
        foreach ($photosets as $set) {
            ++$setsCounter;

            $setId = $set['id'];
            if (isset($existingSets[$setId])) {
                //TODO: this is a hack - it should be a normal list and not magic folders
                if (\preg_match(NameGenerator::PHOTOSET_BLACKLIST_REGEX, $existingSets[$setId]) === 1) {
                    $this->io->info("Skipping album ID=$setId - explicitly blacklisted");
                    continue;
                }

                if(!$this->fs->exists($existingSets[$setId] . '/+complete-album+')) {
                    $this->io->warning("Album ID=$setId exists but it's incomplete - relisting");
                } elseif (!$forceCheckAllSets) {
                    $this->io->info("Skipping album ID=$setId - already exists");
                    continue;
                }

                $setDir = $existingSets[$setId];
            } else {
                $setDir = $this->getPhotosetStableDir($set);
            }


            if ($this->downloadPhotoset($input, $output, $nsid, $setId, $setDir)) {
                $this->io->success("Successfully downloaded album ID=$setId");
                $this->fs->touch($setDir . '/+complete-album+');
            } else {
                $this->io->error("An error occurred while downloading album ID=$setId");
            }

            $this->ensureClientsIdentities(); //reset both download client and API client if needed
        }

        $this->io->success("Finished processing all $setsCounter albums");

        return Command::SUCCESS;
    }

    private function downloadPhotoset(
        InputInterface $input,
        OutputInterface $output,
        string $userNSID,
        string $setId,
        string $destination
    ): bool
    {
        $this->dlPhotosetCmd ??= $this->getApplication()
                                      ->find(\explode('|', LegacyDownloadPhotosetCommand::getDefaultName(), 2)[0]);

        $args = [
            '--user-id' => $userNSID,
            '--force-download' => (string)(int)$input->getOption('force-download'),
            '--randomize-identity' => (string)(int)$input->getOption('randomize-identity'),
            '--randomize-client' => (string)(int)$input->getOption('randomize-client'),
            '--save-metadata' => true,
            'photoset' => $setId,
            'destination' => $destination,
        ];
        dump($args);
        dump('-----------------------------------------------------------------');


        try {
            return $this->dlPhotosetCmd->run(new ArrayInput($args), $output) === Command::SUCCESS;
        } catch (\Throwable $t) {
            $this->io->error(\sprintf('The %s command crashed!', $this->dlPhotosetCmd::class));
            return false;
        }
    }

    private function findExisting(string $targetDir): array
    {
        $existing = [];
        $finder = new Finder();

        foreach ($finder->in($targetDir)
                        ->directories()
                        ->depth(0) as $dir
        ) {
            if (\preg_match(NameGenerator::PHOTOSET_DIR_ID_EXTRACT_REGEX, $dir->getFilename(), $matches) !== 1) {
                continue;
            }

            $existing[$matches[1]] = $dir->getRealPath();
        }

        return $existing;
    }
}
