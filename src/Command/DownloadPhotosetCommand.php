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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DownloadPhotosetCommand extends Command
{
    protected static  $defaultName = 'flickr:download-photoset';
    protected static  $defaultDescription = 'Downloads a single photoset/album';

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
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'NSID of the user, if you don\'t have it just skip it')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, '', 100)
            ->addOption('force-download', 'f', InputOption::VALUE_NONE, 'Overwrite existing files even if they exist')
            ->addArgument('photoset', InputArgument::REQUIRED, 'URL or ID of an album (requires user-id if passing ID)')
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                'Directory to save photos to (by default it will create one)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $targetDir = $input->getArgument('destination'); //TODO add generation
        $this->fs->mkdir($targetDir);
        $skipExisting = !(bool)$input->getOption('force-download');

        $nsid = $input->getOption('user-id');
        $photoset = (string)$input->getArgument('photoset');
        $photosetIsUrl = (\preg_match('/^https?:\/\//', $photoset) === 1);

        if ($nsid === null) {
            if (!$photosetIsUrl) {
                $this->io->error('Photoset specified is in an ID form (not an URL) and no user-id was specified. You need to specify user-id option or pass photoset URL');
            }

            $nsid = $this->flickrUrls->lookupUserId($photoset);
        }

        $photosetId = $photosetIsUrl ? $this->flickrUrls->getPhotosetIdFromUrl($photoset) : $photoset;
        $photos = $this->flickrAlbums->iteratePhotosFlat(
            $nsid,
            $photosetId,
            PhotoSets::MAX_PER_PAGE,
            [PhotoExtraFields::URL_ORIGINAL] //Always try to get the biggest one
        );

        $isFirstPhoto = true; //Some sanity checks are done only once
        $batchCounter = $maxBatchCounter = (int)$input->getOption('batch-size');
        $albumCounter = 0;
        $currentBatch = [];
        $this->io->info('Getting album photos...');
        foreach ($photos as $photo) {
            ++$albumCounter;

            if ($isFirstPhoto) {
                $isFirstPhoto = false;
                $this->checkSizes($photo);
            }

            $this->addToBatch($currentBatch, $targetDir, $photo);
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

    private function downloadBatch(array $batch, bool $skipExisting): void
    {
        $streams = [];
        $files = 0;
        foreach ($batch as $url => $fsPath) {
            if ($skipExisting && $this->fs->exists($fsPath)) {
                continue; //Skip existing if desired
            }

            $sink = \fopen($fsPath, 'w');
            $stream = $this->httpClient->request('GET', $url, ['buffer' => false, 'user_data' => $sink]);
            $streams[] = $stream;
            ++$files;
        }

        if ($files === 0) {
            $this->io->info('No new files in this batch found, skipping');
            return;
        }

        $this->io->info("Downloading $files new files");
        $this->io->progressStart($files);
        foreach($this->httpClient->stream($streams) as $stream => $chunk) {
            $sink = $stream->getInfo('user_data');
            \fwrite($sink, $chunk->getContent());

            if ($chunk->isLast()) {
                $this->io->progressAdvance();
                \fclose($sink);
            }
        }

        $this->io->info('Batch finished');
    }

    private function addToBatch(array &$batch, string $targetDir, array $photo): void
    {
        $url = $photo[PhotoExtraFields::URL_ORIGINAL] ?? $this->getLargestSizeUrl($photo['id']);
        $urlPath = \parse_url($url, \PHP_URL_PATH);
        $filename = \substr(\strrchr($urlPath, '/'), 1);

        $batch[$url] = $targetDir . '/' . $filename;
    }

    private function getLargestSizeUrl(string $photoId): string
    {
        dd('failed!'); //TODO ;D
    }

    private function checkSizes(array $photo): void
    {
        if (isset($photo[PhotoExtraFields::URL_ORIGINAL])) {
            return;
        }

        $this->io->warning('It seems like you do not have permissions to download originals from that user. ' .
                           'You can continue but the download will be SIGNIFICANTLY slower and will lead the ' .
                           'largest sizes possible.');

        if($this->io->confirm('Do you want to continue?', true)) {
            return;
        }

        exit(Command::FAILURE); //It should bubble up but I don't want to deal with that ¯\_(ツ)_/¯
    }
}
