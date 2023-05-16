<?php
declare(strict_types=1);

namespace App\Command;

use App\Flickr\Enum\CollectionType;
use App\Flickr\PhotoSets;
use App\Flickr\Struct\Identity\AlbumIdentity;
use App\Flickr\Struct\Identity\CollectionIdentity;
use App\Flickr\Struct\Identity\UserFavesIdentity;
use App\Flickr\Struct\Identity\UserPhotostreamIdentity;
use App\Flickr\Url\UrlParser;
use App\Struct\PhotoExtraFields;
use App\Struct\PhotoMetadata;
use App\UseCase\SyncCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    //public function __construct(
    //    HttpClientFactory $clientFactory,
    //    Filesystem $fs,
    //    BaseApiClient $apiClient,
    //    NameGenerator $nameGenerator,
    //    private Urls $flickrUrls,
    //    private PhotoSets $flickrAlbums,
    //) {
    //    parent::__construct($clientFactory, $fs, $apiClient, $nameGenerator);
    //}

    private SymfonyStyle $io;


    /**
     * @param callable(): SyncCollection $syncCollection
     */
    public function __construct(
        private UrlParser $urlParser,
        private \Closure $syncCollection,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $types = implode('/', CollectionType::valuesAsList());
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
                 'Do not attempt to sync collections that were synced at least once. This option takes priority over --always-verify-items.'
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
             ->addArgument(
                 'collection',
                 InputArgument::OPTIONAL,
                 "URL or ID of $types (ignored for user photostream and faves). When URL isn't used," .
                 " --user-id and --type are required."
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $identity = $this->getCollectionIdentity($input);
        if ($identity === null) {
            return Command::FAILURE;
        }

        $uc = ($this->syncCollection)(); //this will always get a new instance
        $uc->syncCompleted = !$input->getOption('ignore-completed');
        $uc->trustUpdateTimestamps = !$input->getOption('distrust-timestamps');
        $uc->trustPhotoRecords = !$input->getOption('repair-files');

        $uc->syncCollection($identity);

        return Command::SUCCESS;
    }

    protected function __OLD_execute(InputInterface $input, OutputInterface $output): int
    {

        $parentExit = parent::execute($input, $output);
        if ($parentExit !== Command::SUCCESS) {
            return $parentExit;
        }

        $skipExisting = !$input->getOption('force-download');
        $saveMetadata = (bool)$input->getOption('save-metadata');

        $nsid = $input->getOption('user-id');
        $photoset = (string)$input->getArgument('photoset');
        $photosetIsUrl = (preg_match('/^https?:\/\//', $photoset) === 1);

        if ($nsid === null) {
            if (!$photosetIsUrl) {
                $this->io->error(
                    'Photoset specified is in an ID form (not an URL) and no user-id was specified. You need to ' .
                    'specify user-id option or pass photoset URL'
                );

                return Command::FAILURE;
            }

            $nsid = $this->flickrUrls->lookupUserId($photoset);
        }

        $photosetId = $photosetIsUrl ? $this->flickrUrls->getPhotosetIdFromUrl($photoset) : $photoset;
        $targetDir = $this->getDestination($input, $nsid, $photosetId);

        $photos = $this->flickrAlbums->iteratePhotosFlat(
            $nsid,
            $photosetId,
            PhotoSets::MAX_PER_PAGE,
            [ //in the future this may be configurable
                PhotoExtraFields::DESCRIPTION,
                PhotoExtraFields::DATE_UPLOAD,
                PhotoExtraFields::DATE_TAKEN,
                PhotoExtraFields::OWNER_NAME,
                PhotoExtraFields::LAST_UPDATE,
                PhotoExtraFields::VIEWS,
                PhotoExtraFields::MEDIA
            ] + PhotoExtraFields::casesSizes()
            // /\ THIS IS BUGGY!!!! should be rray_merge
        );

        $isFirstPhoto = true; //Some sanity checks are done only once
        $batchCounter = $maxBatchCounter = (int)$input->getOption('batch-size');
        $albumCounter = 0;
        $currentBatch = [];
        $this->io->info('Getting album photos...');
        //this loop DELIBERATELY doesn't reset API client identity to decrease API keys correlation
        foreach ($photos as $photo) {
            ++$albumCounter;

            if ($isFirstPhoto) {
                $isFirstPhoto = false;
                $this->checkSizes($photo);
            }

            $this->addToBatch($currentBatch, $targetDir, $photo);
            if ($saveMetadata) {
                \file_put_contents(
                    \sprintf('%s/%s.json', $targetDir, $photo['id']),
                    \json_encode($photo, \JSON_PRETTY_PRINT)
                );
            }

            if (--$batchCounter === 0) {
                $batchCounter = $maxBatchCounter;
                $this->downloadBatch($currentBatch, $skipExisting);
                $currentBatch = [];
            }
        }
        if (!empty($currentBatch)) {
            $this->downloadBatch($currentBatch, $skipExisting);
            $currentBatch = [];
        }

        $this->io->success("Finished saving album ID=$photosetId with $albumCounter pictures");

        return Command::SUCCESS;
    }

    private function getCollectionIdentity(InputInterface $input): ?CollectionIdentity
    {
        $collection = (string)$input->getArgument('collection');

        if ($collection !== '' && $this->urlParser->isWebUrl($collection)) { //User passed URL
            return $this->urlParser->getCollectionIdentity($collection);
        }

        $nsid = (string)$input->getOption('user-id');
        if ($nsid === '') {
            $this->io->error(
                'Collection has been passed as an ID. ' .
                'When URL isn\'t used you need to specify user NSID using --user-id'
            );

            return null;
        }

        $type = CollectionType::tryFrom((string)$input->getOption('type'));
        return match ($type) {
            CollectionType::USER_PHOTOSTREAM => new UserPhotostreamIdentity($nsid),
            CollectionType::USER_FAVES => new UserFavesIdentity($nsid),
            CollectionType::ALBUM => new AlbumIdentity($nsid, $collection),
            CollectionType::GALLERY => new AlbumIdentity($nsid, $collection),
            //pool handling is unknown
        };
    }

    private function getDestination(InputInterface $input, string $nsid, string $photosetId): string
    {
        $targetDir = $input->getArgument('destination');

        if (empty($targetDir)) {
            //$targetDir = \sprintf('%s/photoset-nsid%s-id%s', $nsid, $photosetId);
            $setInfo = $this->flickrAlbums->getInfo($nsid, $photosetId);
            $targetDir = $this->getPhotosetStableDir($setInfo);
        }

        $this->fs->mkdir($targetDir);

        return $targetDir;
    }

    private function checkSizes(array $photo): void
    {
        if (isset($photo[PhotoExtraFields::URL_ORIGINAL->value])) {
            return;
        }

        $this->io->warning(
            'It seems like you do not have permissions to download originals from that user. ' .
            'The dumper will try to pick the largest image possible for this user.'
        );
    }

    private function addToBatch(array &$batch, string $targetDir, array $photo): void
    {
        $url = $photo[PhotoExtraFields::URL_ORIGINAL->value] ?? $this->getLargestSizeUrl($photo);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = substr(strrchr($urlPath, '/'), 1);

        $batch[$url] = $targetDir . '/' . $filename;
    }

    private function getLargestSizeUrl(array $photo): string
    {
        $sizes = PhotoMetadata::fromApiResponse($photo)->getSortedSizes();
        $largestKey = \array_key_last($sizes);

        return $sizes[$largestKey]['url'];
    }

    private function downloadBatch(array $batch, bool $skipExisting): void
    {
        $streams = [];
        $files = 0;
        foreach ($batch as $url => $fsPath) {
            if ($skipExisting && $this->fs->exists($fsPath)) {
                continue; //Skip existing if desired
            }

            $sink = \fopen($fsPath, 'wb');
            $stream = $this->downloadHttpClient->request('GET', $url, ['buffer' => false, 'user_data' => $sink]);
            $this->ensureDownloadClientIdentity(); //Download client identity is shuffled every file to blend in
            $streams[] = $stream;
            ++$files;
        }

        if ($files === 0) {
            $this->io->info('No new files in this batch found, skipping');

            return;
        }

        $this->io->info("Downloading $files new files");
        $this->io->progressStart($files);
        foreach ($this->downloadHttpClient->stream($streams) as $stream => $chunk) {
            $sink = $stream->getInfo('user_data');
            \fwrite($sink, $chunk->getContent());

            if ($chunk->isLast()) {
                $this->io->progressAdvance();
                \fclose($sink);

                // \/ Debug identity changing
                $x = $stream->getInfo('debug');
                preg_match_all('/^user-agent: (.*)/im', $x, $ua);
                preg_match_all('/^> GET (.*)$/im', $x, $rl);
                $ident = \sprintf('IDENT for %s: UA<%s>', $rl[1][0] ?? 'UNK', $ua[1][0] ?? 'UNK');
                dump($ident);
            }
        }

        $this->io->info('Batch finished');
    }
}
