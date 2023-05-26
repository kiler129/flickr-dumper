<?php
declare(strict_types=1);

namespace App\Transformer;

use App\Entity\Flickr\Photo;
use App\Flickr\Struct\PhotoDto;

class PhotoDtoEntityTransformer
{
    public function setPhotoMetadata(Photo $local, PhotoDto $apiPhoto, ?\DateTimeInterface $lastRetrieved = null): void
    {
        $local->setApiData($apiPhoto->apiData)
              ->setDateLastRetrieved($lastRetrieved ?? new \DateTimeImmutable());

        if (isset($apiPhoto->title)) {
            $local->setTitle($apiPhoto->title);
        }

        if (isset($apiPhoto->description)) {
            $local->setDescription($apiPhoto->description);
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
            $local->setViews($apiPhoto->views);
        }

        //We're not updating version here are presumably the caller has more knowledge about sizes etc
    }

}
