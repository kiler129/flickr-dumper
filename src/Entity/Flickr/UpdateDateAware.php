<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

interface UpdateDateAware
{
    public function getDateLastUpdated(): ?\DateTimeImmutable;
}
