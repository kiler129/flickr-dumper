<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Collection;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\Stats\RemoteCollectionStats;
use App\Entity\Flickr\Status\CollectionStatus;
use App\Entity\Flickr\Syncable;
use App\Entity\Flickr\SyncableFragment;
use App\Entity\Flickr\UpdateDateAware;
use App\Entity\Flickr\User;
use App\Entity\Flickr\UserOwnedEntity;
use App\Repository\Flickr\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
class Gallery implements PhotoCollection, UserOwnedEntity, Syncable, UpdateDateAware
{
    use PhotoCollectionFragment;
    use SyncableFragment;

    #[ORM\Id]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateCreated = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateLastUpdated = null;

    #[ORM\Embedded]
    public RemoteCollectionStats $remoteStats;

    #[ORM\ManyToOne(inversedBy: 'galleries')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    private User $owner;

    #[ORM\ManyToMany(targetEntity: Photo::class, fetch: 'EXTRA_LAZY', inversedBy: 'galleries')]
    private Collection $photos;

    #[ORM\Column]
    private array $apiData = [];

    public function __construct(int $id, User $owner, ?\DateTimeInterface $retrieved = null)
    {
        $this->id = $id;
        $this->remoteStats = new RemoteCollectionStats();

        $this->setOwner($owner);
        $this->setDateLastRetrieved($retrieved ?? new \DateTimeImmutable());
        $this->photos = new ArrayCollection();
        $this->status = new CollectionStatus();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserReadableId(): string
    {
        $id = 'Gallery ID=' . $this->id;

        if ($this->title !== null) {
            $id .= ' (' . (new UnicodeString($this->title))->truncate(30, '...', false) . ')';
        }

        return $id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTimeInterface $dateCreated): self
    {
        if ($dateCreated !== null && !($dateCreated instanceof \DateTimeImmutable)) {
            $dateCreated = \DateTimeImmutable::createFromInterface($dateCreated);
        }

        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateLastUpdated(): ?\DateTimeImmutable
    {
        return $this->dateLastUpdated;
    }

    public function setDateLastUpdated(?\DateTimeInterface $dateLastUpdated): self
    {
        if ($dateLastUpdated !== null && !($dateLastUpdated instanceof \DateTimeImmutable)) {
            $dateLastUpdated = \DateTimeImmutable::createFromInterface($dateLastUpdated);
        }

        $this->dateLastUpdated = $dateLastUpdated;

        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getApiData(): array
    {
        return $this->apiData;
    }

    public function setApiData(array $apiData): self
    {
        $this->apiData = $apiData;

        return $this;
    }

    public static function ownerOwnsPhotos(): bool
    {
        return true;
    }
}
