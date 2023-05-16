<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class CollectionStatus
{
    /**
     * @var bool When collection is blacklisted no operations are performed on it
     */
    public bool $blacklisted = false;

    /**
     * @var bool Collection can be "soft" deleted regardless of status of its physical files or even file records. Keep
     *           in mind this status is separate from $blacklisted - a collection can be blacklisted (and all sync ops
     *           will ignore it) but not deleted (it's browsable)
     */
    public bool $deleted = false;

    /**
     * @var bool Canary flag preventing collection from being touched automatically by other processes in
     *           multi-threading environment
     */
    public bool $writeLocked = false;
}
