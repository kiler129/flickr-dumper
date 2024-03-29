<?php
declare(strict_types=1);

namespace App\Command\Flickr;

use App\Command\IdentitySwitching;
use App\Entity\Flickr\Photo;
use App\Factory\ConsoleSectionStackFactory;
use App\Factory\SyncStrategyFactory;
use App\Flickr\Enum\MediaCollectionType;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\GalleryIdentity;
use App\Flickr\Struct\Identity\MediaCollectionIdentity;
use App\Flickr\Struct\Identity\PoolIdentity;
use App\Flickr\Struct\Identity\UserFavesIdentity;
use App\Flickr\Struct\Identity\UserPhotostreamIdentity;
use App\Flickr\Url\UrlParser;
use App\Struct\DownloadJobStatus;
use App\Struct\OrderedObjectLibrary;
use App\UseCase\FetchPhotoToDisk;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-import-type TSyncCallback from FetchPhotoToDisk
 */
#[AsCommand(
    name: 'flickr:sync-collection',
    aliases: [
        'flickr:download:user-photostream',
        'flickr:downlaod:album',
        'flickr:download:photoset', //a different name for an album
        'flickr:download:favorites', //user favorites
        'flickr:download:gallery', //NOT the same as album!
    ],
    description: 'Downloads/syncs any generic collection of photos',
)]
class SyncCollectionCommand extends Command
{
    use IdentitySwitching;

    private SymfonyStyle           $io;

    /** @var OrderedObjectLibrary<ConsoleSectionOutput> */
    private OrderedObjectLibrary $sectionLibrary;

    /** @var \SplObjectStorage<DownloadJobStatus, array{ProgressBar, ConsoleSectionOutput} */
    private \SplObjectStorage $jobsProgress;

    /**
     * @param callable(): FetchPhotoToDisk    $fetchPhotoToDisk
     */
    public function __construct(
        private LoggerInterface $log,
        private ConsoleHandler $consoleHandler,
        private ConsoleSectionStackFactory $sectionFactory,
        private UrlParser $urlParser,
        private SyncStrategyFactory $syncFactory,
        private \Closure $fetchPhotoToDisk,
    ) {
        parent::__construct();

        $this->jobsProgress = new \SplObjectStorage();
    }

    protected function configure()
    {
        $types = implode('/', MediaCollectionType::valuesAsList());
        $this->addOption(
                'user-id',
                null,
                InputOption::VALUE_REQUIRED,
                'NSID of the user (if known); if you don\'t have it just skip it and it will be retrieved from the API'
            )
             ->addOption(
                 'type',
                 null,
                 InputOption::VALUE_REQUIRED,
                 "Type of the collection ($types); it is only applicable when passing id of collection and not a URL"
             )
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
             ->addArgument(
                 'collection',
                 InputArgument::REQUIRED,
                 "URL or ID of $types (ignored for user photostream and faves). When URL isn't used," .
                 " --user-id and --type are required."
             );

        $this->addSwitchIdentitiesOption($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resolveSwitchIdentities($input);
        $this->io = new SymfonyStyle($input, $output);

        $identity = $this->getCollectionIdentity($input);
        $sinkCb = $this->getSinkCallable($input, $output);

        if ($identity === null || $sinkCb === null) {
            return Command::FAILURE;
        }

        $syncUC = $this->syncFactory->createForCollection($identity); //this will always get a new instance
        $syncUC->syncCompleted = !$input->getOption('ignore-completed');
        $syncUC->trustUpdateTimestamps = !$input->getOption('distrust-timestamps');
        $syncUC->trustPhotoRecords = !$input->getOption('repair-files');
        $syncUC->switchIdentities = $this->switchIdentities;

        return $syncUC->syncCollection($identity, $sinkCb) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return TSyncCallback
     */
    private function getSinkCallable(InputInterface $input, OutputInterface $output): ?callable
    {
        $repairFiles = $input->getOption('repair-files');
        if ($input->getOption('index-only')) {
            if ($repairFiles) {
                $this->io->error(
                    '--index-only and --repair-files are mutually exclusive. You cannot repair files ' .
                    '(--repair-files) if you are requesting no files to be downloaded (--index-only)'
                );
                return null;
            }

            return function(Photo $photo): bool {
                $this->log->info('[Index-Only Mode] Photo ID={pid} would have been downloaded', ['pid' => $photo->getId()]);
                return true;
            };
        }

        $dlUC = ($this->fetchPhotoToDisk)(); //this will always get a new instance
        $dlUC->switchIdentities = $this->switchIdentities;

        //@todo BRAKS the console - I spent hours trying to fix that...
        //if ($this->setupSectionedOutput($output)) {
        //    $dlUC->onProgress([$this, 'renderStatusProgress']);
        //}

        return $dlUC;
    }

    private function setupSectionedOutput(OutputInterface $output): bool
    {
        if (isset($this->sectionLibrary)) {
            return true; //already done
        }

        if (!($output instanceof ConsoleOutputInterface)) {
            $this->log->warning(
                'Output for this command is not a console (found {found} instead of {expected}) - sectioned output ' .
                'is not possible, thus live progress will not be reported',
                ['found' => $output::class, 'expected' => ConsoleOutputInterface::class]
            );
            return false;
        }

        $this->sectionLibrary = $this->sectionFactory->createForOutput($output);
        $this->consoleHandler->setOutput($this->sectionLibrary->borrow());

        return true;
    }

    /**
     * @internal
     */
    public function renderStatusProgress(DownloadJobStatus $status): void
    {
        $total = $status->bytesTotal;
        $downloaded = $status->bytesDownloaded;
        if ($total === -1) {
            $total = 0;
        }
        if ($downloaded === -1) {
            $downloaded = 0;
        }

        //Job completed - clean up
        if ($status->completed) {
            if (!$this->jobsProgress->contains($status)) {
                return; //bar was never started
            }

            [$bar, $screen] = $this->jobsProgress[$status];
            $bar->finish();
            $bar->clear();
            $screen->clear();
            $this->sectionLibrary->return($screen);
            $this->jobsProgress->detach($status);

            return;
        }

        //Brand new job (never seen) - create progress bar
        if (!$this->jobsProgress->contains($status)) {
            $screen = $this->sectionLibrary->borrow();
            $bar = new ProgressBar($screen);
            $bar->setEmptyBarCharacter('▱');
            $bar->setProgressCharacter('');
            $bar->setBarCharacter('▰');
            $bar->minSecondsBetweenRedraws(0.25);

            $bar->start($total, $downloaded);
            $this->jobsProgress->attach($status, [$bar, $screen]);

            return;
        }

        //Continue already existing job
        $bar = $this->jobsProgress[$status][0];
        if ($bar->getMaxSteps() !== $total) {
            $bar->setMaxSteps($total);
        }

        if ($bar->getProgress() !== $downloaded) {
            $bar->setProgress($downloaded);
        }
    }

    private function getCollectionIdentity(InputInterface $input): ?MediaCollectionIdentity
    {
        $collection = (string)$input->getArgument('collection');

        if ($collection !== '' && $this->urlParser->isWebUrl($collection)) { //User passed URL
            $urlCol = $this->urlParser->getMediaCollectionIdentity($collection);

            if ($urlCol === null) {
                $this->io->error('The URL passed do not seem to point to a single collection. Make sure ' .
                                 'you did not pass a link to a group of collection (e.g. all user\'s albums) nor ' .
                                 'to just a single photo.');

                return null;
            }

            return $urlCol;
        }

        $nsid = (string)$input->getOption('user-id');
        if ($nsid === '') {
            $this->io->error(
                'Collection has been passed as an ID (' . $collection . '). ' .
                'When URL isn\'t used you need to specify user NSID using --user-id'
            );

            return null;
        }

        $type = MediaCollectionType::tryFrom((string)$input->getOption('type'));
        return match ($type) {
            MediaCollectionType::USER_PHOTOSTREAM => new UserPhotostreamIdentity($nsid),
            MediaCollectionType::USER_FAVES => new UserFavesIdentity($nsid),
            MediaCollectionType::ALBUM => new AlbumIdentity($nsid, $collection),
            MediaCollectionType::GALLERY => new GalleryIdentity($nsid, $collection),
            MediaCollectionType::POOL => new PoolIdentity($collection)
        };
    }
}
