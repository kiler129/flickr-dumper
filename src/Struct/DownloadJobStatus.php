<?php
declare(strict_types=1);

namespace App\Struct;

use App\Flickr\Struct\FileInTransit;

/**
 * @todo this in the future may have like abort() method that is calling a dynamic callback set in FetchPhotoToDisk and
 *       it couples it to abort() in PhotoStorageProvider (that SHOULD NOT be linked here directly!)
 *
 * @template TReportee callable(DownloadJobStatus $status): void
 */
class DownloadJobStatus
{
    /**
     * @readonly
     */
    public int $bytesDownloaded = -1;

    /**
     * @readonly
     */
    public int $bytesTotal = -1;

    /**
     * @readonly It should not be changed externally outside classes generating it
     */
    public bool $completed = false;

    /**
     * @readonly It should not be changed externally outside classes generating it
     */
    public ?string $error = null;

    /**
     * @var TReportee
     */
    private mixed $reportee;

    public function __construct(readonly public int $jobId, callable $reportee)
    {
        $this->reportee = $reportee;
    }

    public function finish(): void
    {
        $this->completed = true;
        $this->report();
    }

    public function fail(string $error): void
    {
        $this->completed = true;
        $this->error = $error;
        $this->report();
    }

    public function report(): void
    {
        ($this->reportee)($this);
    }
}
