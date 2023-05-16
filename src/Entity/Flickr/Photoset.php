<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use App\Repository\Flickr\PhotosetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity(repositoryClass: PhotosetRepository::class)]
class Photoset implements PhotoCollection, UserOwnedEntity
{
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

    #[ORM\Column]
    private \DateTimeImmutable $dateLastRetrieved;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateSyncCompleted = null;

    #[ORM\ManyToOne(inversedBy: 'photosets')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    private User $owner;

    #[ORM\Embedded]
    private CollectionStatus $status;

    #[ORM\Column]
    private array $apiData = [];

    public function __construct(int $id, User $owner, ?\DateTimeInterface $retrieved = null)
    {
        $this->id = $id;
        $this->setOwner($owner);
        $this->setDateLastRetrieved($retrieved ?? new \DateTimeImmutable());
        $this->status = new CollectionStatus();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserReadableId(): string
    {
        $id = 'Album/photoset ID=' . $this->id;

        if ($this->title !== null) {
            $id .= '(' . (new UnicodeString($this->title))->truncate(30, '...', false) . ')';
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

        return $this;
    }

    public function getDateLastRetrieved(): \DateTimeImmutable
    {
        return $this->dateLastRetrieved;
    }

    public function setDateLastRetrieved(\DateTimeInterface $dateLastRetrieved): self
    {
        if (!($dateLastRetrieved instanceof \DateTimeImmutable)) {
            $dateLastRetrieved = \DateTimeImmutable::createFromInterface($dateLastRetrieved);
        }

        $this->dateLastRetrieved = $dateLastRetrieved;

        return $this;
    }

    public function getDateSyncCompleted(): \DateTimeImmutable
    {
        return $this->dateSyncCompleted;
    }

    public function setDateSyncCompleted(?\DateTimeInterface $dateSyncCompleted): self
    {
        if ($dateSyncCompleted !== null && !($dateSyncCompleted instanceof \DateTimeImmutable)) {
            $dateSyncCompleted = \DateTimeImmutable::createFromInterface($dateSyncCompleted);
        }

        $this->dateSyncCompleted = $dateSyncCompleted;

        return $this;
    }

    public function getOwner(): ?User
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

    public function isSyncCompleted(): bool
    {
        return $this->dateSyncCompleted !== null;
    }

    public function isBlacklisted(): bool
    {
        return $this->status->blacklisted;
    }

    public function isDeleted(): bool
    {
        return $this->status->deleted;
    }

    public function isWriteLocked(): bool
    {
        return $this->status->writeLocked;
    }
}
