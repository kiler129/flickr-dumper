<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\Photo;
use App\Exception\InvalidArgumentException;
use App\Exception\IOException;
use App\Factory\HttpClientConfigFactory;
use App\Filesystem\StorageProvider;
use App\Flickr\Struct\FileInTransit;
use App\Repository\Flickr\PhotoRepository;
use App\Struct\DownloadJobStatus;
use App\Struct\HttpClientConfig;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * This class is specific for photos but can probably be easily generalized
 *
 * @template TSyncCallback of callable(Photo $photo): bool
 * @phpstan-import-type TReportee from DownloadJobStatus
 */
class FetchPhotoToDisk
{
    /**
     * When set it will attempt to reset/regenerate identity of both the Flickr API client (new key, new UA, new proxy)
     * and the download client (new UA, new proxy). Naturally, if there's no or single proxy defined this option has
     * no effect on proxy; likewise the same applies to API keys. However, UA is always changed.
     * When exactly identities are swapped is deliberately opaque as it's determined to look real-ish.
     */
    public bool $switchIdentities = false;

    /**
     * @var int Number of files to download at once
     */
    private int $batchSize;

    private HttpClientConfig $httpConfig;

    /**
     * @var callable|null
     */
    private $progressCallback;

    /**
     * @var list<int>
     */
    private array $enqueued = [];

    /**
     * @var \SplObjectStorage<ResponseInterface, FileInTransit>
     */
    private \SplObjectStorage $jobs;

    /**
     * @var array<int, DownloadJobStatus>
     */
    private array $statuses = [];

    public function __construct(
        private LoggerInterface $log,
        private PhotoRepository $photoRepo,
        private HttpClientInterface $httpClient,
        private HttpClientConfigFactory $httpConfigFactory,
        private StorageProvider $storage,
        int $batchSize = 1
    ) {
        $this->setBatch($batchSize);
        $this->jobs = new \SplObjectStorage();
    }

    /**
     * Sets on-progress callback
     *
     * This cannot be a public property because: https://wiki.php.net/rfc/typed_properties_v2#supported_types
     *
     * @param TReportee
     *
     * @return $this
     */
    public function onProgress(callable $callback): static
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Enables downloading files in batches which may speed up the process
     *
     * By default, files are downloaded one-by-one as they're delivered. This method enabled downloading them in groups.
     * Keep in mind you MUST call flushAll() after finishing your processing: it ensures that the last batch is
     * finished. Technically speaking you should probably always call fetchWaiting() for consistency.
     * However... if you don't it's called in the destructor too ;)
     *
     * @param int $filesPerPatch
     *
     * @return $this
     */
    public function setBatch(int $filesPerBatch = 10): static
    {
        if ($filesPerBatch < 1) {
            throw new InvalidArgumentException(
                \sprintf('A download batch must have a size of at least one (got %d)', $filesPerBatch)
            );
        }

        $this->batchSize = $filesPerBatch;

        return $this;
    }

    private function fetchAwaiting(): bool
    {
        $this->log->debug('Fetching {count} awaiting streams', ['count' => \count($this->jobs)]);

        $overallStatus = true;
        $this->jobs->rewind();
        foreach ($this->httpClient->stream($this->jobs) as $response => $chunk) {
            $fitId = null;
            $currentFileStatus = null;
            /** @var FileInTransit $file */
            $file = $this->jobs[$response];

            try {
                $file->write($chunk->getContent());
                if (!$chunk->isLast()) { //not doing anything for others => it will be handled by on_progress callback
                    continue;
                }

                $fitId = \spl_object_id($file);

                //The same block is repeated in "catch" to fail the whole chain
                $this->jobs->detach($response); //make sure job is cleared in case something throws
                $this->storage->finish($file); //call this before reporting status in case it throws
                if (isset($this->statuses[$fitId])) {
                    ($this->statuses[$fitId] ?? null)?->finish();
                    unset($this->statuses[$fitId]);
                }

                $currentFileStatus = true;

            } catch (TransportExceptionInterface | IOException $e) {
                $error = \sprintf('%s: %s', $e::class, $e->getMessage());
                $this->log->error(
                    'Download of {url} failed due to {error}',
                    ['url' => $response->getInfo('url'), 'error' => $error]
                );

                $this->jobs->detach($response);
                $this->storage->abort($file);
                if (isset($this->statuses[$fitId])) {
                    $this->statuses[$fitId]?->fail($error);
                }

                $currentFileStatus = false;

            } finally {
                if ($currentFileStatus !== null) {
                    $overallStatus = $currentFileStatus && $overallStatus;
                    $this->finalizePhoto($currentFileStatus, $response, $file);
                }
            }
        }

        return $overallStatus;
    }

    public function __invoke(Photo $photo): bool
    {
        //fully synchronous operation - we can skip saving to enqueued as long as the queue isn't populated (as the
        // batchSize may have changed!)
        if ($this->batchSize === 1 && !isset($this->enqueued[0])) {
            $this->requestFile($photo);
            return $this->fetchAwaiting();
        }

        //We're NOT saving the actual entity as it may be cleared from EM between putting it in a queue and actually
        //processing the queue. If the EM is not cleared doing a simple get() is very quick from cache.
        $this->enqueued[] = $photo->getId();

        //Queue contains at least batchSize elements (or more, if the size was changed) => make request for them so that
        // fetchAwaiting() can start grabbing the data
        //if (isset($this->enqueued[$this->batchSize - 1])) {
        if (\count($this->enqueued) >= $this->batchSize) {
            $this->requestEnqueued();

            return $this->fetchAwaiting();
        }

        return true;
    }

    private function requestEnqueued(): void
    {
        foreach ($this->enqueued as $idx => $phId) {
            try {
                $photo = $this->photoRepo->find($phId);
                if ($photo === null) {
                    $this->log->critical(
                        'Photo id={phid} was enqueued but it does not exist during queue processing (db corrupted?)',
                        ['phid' => $phId]
                    );
                    continue;
                }

                $this->requestFile($photo);
                $this->photoRepo->save($photo);

            } finally {
                unset($this->enqueued[$idx]);
            }
        }
    }

    private function requestFile(Photo $photo): void
    {
        if ($this->storage->photoExists($photo)) {
            $this->log->info(
                'Photo id={phid} already exists locally - skipping download',
                ['phid' => $photo->getId()]
            );

            return;
        }

        $photo->lockForWrite();
        $file = $this->storage->newForPhoto($photo);
        $statusKey = \spl_object_id($file);

        $photoUrl = $photo->getCdnUrl();
        $httpOpts = $this->builtHttpOptions($statusKey);
        $httpOpts['user_data'] = $photo; //save it so we can unlock the entity later

        $this->log->debug(
            'Requesting {url} via ua={ua} prx={prx}',
            [
                'url' => $photoUrl,
                'ua' => $httpOpts['headers']['User-Agent'] ?? '???',
                'prx' => $httpOpts['proxy'] ?? null,
            ]
        );
        $response = $this->httpClient->request('GET', $photoUrl, $httpOpts);

        $this->jobs->attach($response, $file);
    }

    private function builtHttpOptions(int $statusKey): array
    {
        if (isset($this->httpConfig)) {
            if ($this->switchIdentities && \mt_rand(0, 1) === 1) {
                $this->httpConfig = $this->httpConfigFactory->getWithRandomBrowser();
            }
        } else {
            $this->httpConfig = $this->switchIdentities
                ? $this->httpConfigFactory->getWithRandomBrowser() : $this->httpConfigFactory->getWithCommonCliClient();
        }

        $httpOpts = $this->httpConfig->asOptions();
        if (isset($this->progressCallback)) {
            $this->statuses[$statusKey] = new DownloadJobStatus($statusKey, $this->progressCallback);

            $httpOpts['on_progress'] = function (int $dlNow, int $dlSize, array $info) use ($statusKey): void {
                $status = $this->statuses[$statusKey];
                $status->bytesDownloaded = $dlNow;
                $status->bytesTotal = $dlSize;
                $status->report();
            };
        }

        return $httpOpts;
    }

    private function finalizePhoto(bool $jobStatus, ResponseInterface $response, FileInTransit $file): void
    {
        $photo = $response->getInfo('user_data');
        \assert($photo instanceof Photo);
        $photo->unlockForWrite($jobStatus);

        if ($jobStatus) {
            $photo->setLocalPath($file->savePath);
        }

        $this->photoRepo->save($photo, true);

        //This only uses $jobStatus and not e.g. $response->getStatusCode() as there's an edge case when using cURL
        // multiplexing + HTTP/2. If ANY of the substreams in HTTP/2 connection fails calling getStatusCode() can explode
        // even if that particular file succeeded. Moreover, this has to happen exactly between two failes ;D
        //Don't ask me how I know thou.
        $this->log->info(
            'Fetching {url} to {file} {status}',
            [
                'url' => $response->getInfo('url'),
                'file' => $file->savePath,
                'status' => $jobStatus ? 'finished successfully' : 'FAILED',
            ]
        );
    }
    

    public function flushAll(): bool
    {
        $this->requestEnqueued();

        return $this->fetchAwaiting();
    }

    public function __destruct()
    {
        $this->flushAll();
    }
}

