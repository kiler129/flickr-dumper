<?php
declare(strict_types=1);

namespace App\UseCase\ImportFiles;

use App\Flickr\Struct\ApiDto\PhotoDto;
use Psr\Log\LoggerInterface;

/**
 * A generic usecase meant to provide metadata for photos from external sources
 */
class LoadExternalPhotoMetadata
{
    public function __construct(private LoggerInterface $log)
    {
    }

    public function tryLoadMetadataFromFile(string $filePath, int $photoId): ?PhotoDto
    {
        $jsonPath = $filePath;
        if (!\file_exists($jsonPath)) {
            return null;
        }

        $this->log->debug('Attempting to load photo id={phid} metadata from {json}', ['phid' => $photoId, 'json' => $jsonPath]);
        try {
            $jsonFile = \file_get_contents($jsonPath);
            $data = \json_decode($jsonFile, true);
            $this->recoverMetadataUserNsid($filePath, $data);
            $dto = PhotoDto::fromGenericApiResponse($data);
        } catch (\Throwable $t) {
            $this->log->warning(
                'Loading metadata from {json} failed due to {exc}: {msg}',
                [
                    'json' => $jsonPath,
                    'exc' => $t::class,
                    'msg' => $t->getMessage(),
                    'exception' => $t,
                ]
            );

            return null;
        }

        if ($dto->id !== $photoId) {
            $this->log->warning(
                'Fuzzy-matched API data for photo id={phid} from {json} is for a different photo (id={fuzzPhId})',
                ['phid' => $photoId, 'json' => $jsonPath, 'fuzzPhId' => $dto->id]
            );

            return null;
        }

        return $dto;
    }

    private function recoverMetadataUserNsid(string $filePath, array &$apiData): void
    {
        if (isset($apiData['owner'])) {
            $this->log->debug('API data has owner NSID - no need to recover');

            return;
        }

        if (\preg_match('/\d+@N\d{2}/', $filePath, $userMatches) === 0) {
            //This can use \App\Flickr\ClientEndpoint\UrlsEndpoint::lookupMediaById to potentially get the user
            $this->log->warning(
                'Ignoring {file} - cannot find author (and discovery is not implemented yet)',
                ['file' => $filePath]
            );

            return;
        }
        $nsid = $userMatches[0];

        $this->log->debug(
            'Recovered user NSID {nsid} from path {path} - injecting into API data', ['nsid' => $nsid, 'path' => $filePath]
        );
        $apiData['owner'] = $nsid;
    }
}
