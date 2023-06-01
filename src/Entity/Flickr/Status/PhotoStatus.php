<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Status;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PhotoStatus
{
    /**
     * @var bool When photo is blacklisted/blocked/locked no operations are performed on it
     * @todo It should probably be renamed to something like localLocked
     */
    #[ORM\Column]
    public bool $blacklisted = false;

    /**
     * @var bool Photo can be "soft" deleted regardless of status of its physical files or even file records. Keep
     *           in mind this status is separate from $blacklisted - a photo can be blacklisted (and all sync ops
     *           will ignore it) but not deleted (it's browsable). Deleted flag should be used for photos that should
     *           functionally be considered "not existing", i.e. these only exists so during sync we know to not
     *           recreate something that was deleted locally.
     *           The system makes no guarantees for files marked as deleted to be recoverable! Its metadata may've been
     *           cleared to save space etc.
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
