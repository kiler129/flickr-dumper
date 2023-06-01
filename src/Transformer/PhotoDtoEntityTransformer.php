<?php
declare(strict_types=1);

namespace App\Transformer;

use App\Entity\Flickr\Photo;
use App\Flickr\Struct\ApiDto\PhotoDto;
use Psr\Log\LoggerInterface;

class PhotoDtoEntityTransformer
{
    public function __construct(private LoggerInterface $log)
    {
    }

    public function setPhotoMetadata(Photo $local, PhotoDto $apiPhoto, ?\DateTimeInterface $lastRetrieved = null): void
    {
        $this->log->debug('Updating metadata of photo id={phid} from API version', ['phid' => $local->getId()]);

        $local->setApiData($apiPhoto->apiData)
              ->setDateLastRetrieved($lastRetrieved ?? new \DateTimeImmutable());

        if ($apiPhoto->hasProperty('title')) {
            $local->setTitle($apiPhoto->title);
        }

        if ($apiPhoto->hasProperty('description')) {
            $local->setDescription($apiPhoto->description);
        }

        if (isset($apiPhoto->safetyLevel)) {
            $local->setSafetyLevel($apiPhoto->safetyLevel);
        }

        if ($local->getDateTaken() === null && isset($apiPhoto->dateTaken)) {
            $local->setDateTaken($apiPhoto->dateTaken);
        }

        if ($local->getDateUploaded() === null && isset($apiPhoto->dateUploaded)) {
            $local->setDateUploaded($apiPhoto->dateUploaded);
        }

        $dateUpdateLocal = $local->getDateLastUpdated();
        if (isset($apiPhoto->dateUpdated) && ($dateUpdateLocal === null || $apiPhoto->dateUpdated > $dateUpdateLocal)) {
            $local->setDateLastUpdated($apiPhoto->dateUpdated);
        }

        if (isset($apiPhoto->views)) {
            $local->remoteStats->views = $apiPhoto->views;
        }

        if (isset($apiPhoto->favesCount)) {
            $local->remoteStats->favorites = $apiPhoto->favesCount;
        }

        if (isset($apiPhoto->commentsCount)) {
            $local->remoteStats->comments = $apiPhoto->commentsCount;
        }

        //We're not updating version here are presumably the caller has more knowledge about sizes etc
    }

}
