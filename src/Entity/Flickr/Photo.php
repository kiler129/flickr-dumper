<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use App\Repository\Flickr\PhotoRepository;
use App\Struct\PhotoSize;
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
    private ?\DateTimeImmutable $dateTaken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateUploaded = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateLastUpdated = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateLastRetrieved;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $filePath;

    #[ORM\Column]
    private PhotoSize $fileVersion;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    private User $owner;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\Column]
    private array $apiData = [];

    public function __construct(int $id, User $owner, string $filePath, PhotoSize $fileVersion, ?\DateTimeInterface $retrieved = null)
    {
        $this->id = $id;

        $this->setOwner($owner);
        $this->setFilePath($filePath);
        $this->setFileVersion($fileVersion);
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

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;

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
}
