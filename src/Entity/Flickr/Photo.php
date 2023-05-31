<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use App\Entity\Flickr\Stats\LocalItemStats;
use App\Entity\Flickr\Stats\RemoteStats;
use App\Exception\LogicException;
use App\Flickr\Enum\SafetyLevel;
use App\Repository\Flickr\PhotoRepository;
use App\Struct\PhotoSize;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?SafetyLevel $safetyLevel = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateTaken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateUploaded = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateLastUpdated = null;

    #[ORM\Embedded]
    public RemoteStats $remoteStats;

    #[ORM\Embedded]
    public LocalItemStats $localStats;

    #[ORM\Column]
    private \DateTimeImmutable $dateLastRetrieved;

    #[ORM\Column]
    private PhotoSize $fileVersion;

    #[ORM\Column(length: 255)]
    private string $cdnUrl;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $localPath = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    private User $owner;

    #[ORM\Embedded]
    private PhotoStatus $status;

    #[ORM\Column]
    private array $apiData = [];


    //These are mapped to allow DQL access, but should NOT be operated from within the entity
    #[ORM\ManyToMany(targetEntity: Photoset::class, mappedBy: 'photos')]
    private Collection $photosets;

    #[ORM\ManyToMany(targetEntity: UserFavorites::class, mappedBy: 'photos')]
    private Collection $userFavorites;

    public function __construct(int $id, User $owner, PhotoSize $fileVersion, string $cdnUrl, ?\DateTimeInterface $retrieved = null)
    {
        $this->id = $id;
        $this->remoteStats = new RemoteStats();
        $this->localStats = new LocalItemStats();
        $this->status = new PhotoStatus();

        $this->setOwner($owner);
        $this->setFileVersion($fileVersion);
        $this->setCdnUrl($cdnUrl);
        $this->setDateLastRetrieved($retrieved ?? new \DateTimeImmutable());
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSafetyLevel(): ?SafetyLevel
    {
        return $this->safetyLevel;
    }

    public function setSafetyLevel(?SafetyLevel $safetyLevel): static
    {
        $this->safetyLevel = $safetyLevel;

        return $this;
    }

    public function getDateTaken(): ?\DateTimeImmutable
    {
        return $this->dateTaken;
    }

    public function setDateTaken(?\DateTimeInterface $dateTaken): self
    {
        if ($dateTaken !== null && !($dateTaken instanceof \DateTimeImmutable)) {
            $dateTaken = \DateTimeImmutable::createFromInterface($dateTaken);
        }

        $this->dateTaken = $dateTaken;

        return $this;
    }

    public function getDateUploaded(): ?\DateTimeImmutable
    {
        return $this->dateUploaded;
    }

    public function setDateUploaded(?\DateTimeInterface $dateUploaded): self
    {
        if ($dateUploaded !== null && !($dateUploaded instanceof \DateTimeImmutable)) {
            $dateUploaded = \DateTimeImmutable::createFromInterface($dateUploaded);
        }

        $this->dateUploaded = $dateUploaded;

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

    public function getFileVersion(): PhotoSize
    {
        return $this->fileVersion;
    }

    public function setFileVersion(PhotoSize $fileVersion): self
    {
        $this->fileVersion = $fileVersion;

        return $this;
    }

    public function getCdnUrl(): ?string
    {
        return $this->cdnUrl;
    }

    public function setCdnUrl(string $cdnUrl): self
    {
        if (!isset($this->cdnUrl) || $this->cdnUrl !== $cdnUrl) {
            $this->status->filesystemInSync = false;
        }

        $this->cdnUrl = $cdnUrl;

        return $this;
    }

    public function getLocalPath(): ?string
    {
        return $this->localPath;
    }

    public function setLocalPath(?string $localPath): self
    {
        $this->localPath = $localPath;
        $this->status->filesystemInSync = false;

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

    public function isBlacklisted(): bool
    {
        return $this->status->blacklisted;
    }

    public function isDeleted(): bool
    {
        return $this->status->deleted;
    }

    public function setDeleted(bool $isDeleted = true): self
    {
        $this->status->deleted = $isDeleted;

        return $this;
    }

    public function lockForWrite(): void
    {
        if ($this->status->writeLockedAt !== null) {
            throw new LogicException(
                \sprintf(
                    'Photo %d is already write-locked since %s - cannot re-lock',
                    $this->id,
                    $this->status->writeLockedAt->format('Y-mt-d H:i:s')
                )
            );
        }

        $this->status->writeLockedAt = new \DateTimeImmutable();
    }

    public function unlockForWrite(bool $fsInSync): self
    {
        $this->status->writeLockedAt = null;
        $this->status->filesystemInSync = $fsInSync;

        return $this;
    }

    public function isWriteLocked(): bool
    {
        return $this->status->writeLockedAt !== null;
    }

    public function isFilesystemInSync(): bool
    {
        return $this->status->filesystemInSync;
    }

    public static function ownerOwnsPhotos(): bool
    {
        return true;
    }
}
