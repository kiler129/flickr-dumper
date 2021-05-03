<?php

namespace App\Command;

use App\Exception\Api\ApiCallException;
use App\Flickr\PhotoSets;
use App\Flickr\Test;
use App\Flickr\Urls;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoSize;
use App\Util\NameGenerator;
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

class DownloadUserPhotosetsCommand extends Command
{
    protected static  $defaultName = 'flickr:download-user-photosets';
    protected static  $defaultDescription = 'Downloads all user photosets/album';

    private Urls                $flickrUrls;
    private PhotoSets           $flickrAlbums;
    private HttpClientInterface $httpClient;
    private Filesystem          $fs;
    private SymfonyStyle        $io;
    private NameGenerator       $nameGenerator;

    public function __construct(Urls $flickrUrls, PhotoSets $flickrAlbums, HttpClientInterface $httpClient, Filesystem $fs, NameGenerator $nameGenerator)
    {
        $this->flickrUrls = $flickrUrls;
        $this->flickrAlbums = $flickrAlbums;
        $this->httpClient = $httpClient;
        $this->fs = $fs;
        $this->nameGenerator = $nameGenerator;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('force-download-files', 'f', InputOption::VALUE_NONE, 'Overwrite existing files even if they exist')
            ->addOption('check-all', 'c', InputOption::VALUE_NONE, 'By default the whole album is skipped if its directory exist, this option forces re-listing of all albums')
            ->addArgument('user', InputArgument::REQUIRED, 'URL to user resource (profile/photo/etc.) or NSID')
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Directory to save photosets to (by default it will create one)',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $user = $input->getArgument('user');
        $userIsUrl = (\preg_match('/^https?:\/\//', $user) === 1);
        $nsid = $userIsUrl ? $this->flickrUrls->lookupUserId($user) : $user;

        $targetDir = $input->getArgument('destination');
        if (empty($targetDir)) {
            $targetDir = $nsid . '-photosets';
        }
        $this->fs->mkdir($targetDir);
        $forceDownloadFiles = (bool)$input->getOption('force-download-files');
        $forceCheckAllSets = (bool)$input->getOption('check-all');

        $existingSets = $this->findExisting($targetDir);
        $photosets = $this->flickrAlbums->iterateListFlat($nsid);
        $downloadPhotosetCommand = $this->getApplication()->find(DownloadPhotosetCommand::getDefaultName());
        $setsCounter = 0;
        $this->io->info('Getting albums...');
        foreach ($photosets as $set) {
            ++$setsCounter;

            $setId = $set['id'];
            if (isset($existingSets[$setId])) {
                if (!$forceCheckAllSets) {
                    $this->io->info("Skipping album ID=$setId - already exists");
                    continue;
                }

                $setDir = $existingSets[$setId];
            } else {
                $setDir = $targetDir . '/' . $this->nameGenerator->getDirectoryNameForPhotoset($set);
            }

            $args = new ArrayInput(
                [
                    '--user-id' => $nsid,
                    '--force-download' => $forceDownloadFiles ? '1' : '0',
                    'photoset' => $setId,
                    'destination' => $setDir
                ]
            );

            $return = $downloadPhotosetCommand->run($args, $output);
            if ($return === Command::SUCCESS) {
                $this->io->success("Successfully downloaded album ID=$setId");
            } else {
                $this->io->error("An error occurred while downloading album ID=$setId");
            }
        }

        $this->io->success("Finished processing all $setsCounter albums");

        return Command::SUCCESS;
    }

    private function findExisting(string $targetDir): array
    {
        $existing = [];
        $finder = new Finder();

        foreach ($finder->in($targetDir)->directories()->depth(0) as $dir) {
            if (\preg_match(NameGenerator::PHOTOSET_DIR_ID_EXTRACT_REGEX, $dir->getFilename(), $matches) !== 1) {
                continue;
            }

            $existing[$matches[1]] = $dir->getRealPath();
        }

        return $existing;
    }
}
