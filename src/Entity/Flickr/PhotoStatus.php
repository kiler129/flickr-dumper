<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PhotoStatus
{
    /**
     * @var bool When photo is blacklisted/blocked/locked no operations are performed on it
     */
    #[ORM\Column]
    public bool $blacklisted = false;

    /**
     * @var bool Photo can be "soft" deleted regardless of status of its physical files or even file records. Keep
     *           in mind this status is separate from $blacklisted - a photo can be blacklisted (and all sync ops
     *           will ignore it) but not deleted (it's browsable)
     */
    #[ORM\Column]
    public bool $deleted = false;

    /**
     * @var \DateTimeImmutable|null Canary flag preventing photo from being touched automatically by other processes in
     *                              multi-threading environment
     */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $writeLockedAt = null;

    /**
     * @var bool Denotes whether the filesystem contains the file referenced by URL (and in consequence the metadata
     *           match the actual image)
     */
    #[ORM\Column]
    public bool $filesystemInSync = false;
}
