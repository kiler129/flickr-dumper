<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use Doctrine\ORM\Mapping as ORM;

trait SyncableFragment
{
    #[ORM\Column]
    private \DateTimeImmutable $dateLastRetrieved;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateSyncCompleted = null;

    public function isSyncCompleted(): bool
    {
        return $this->dateSyncCompleted !== null;
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

    public function getDateSyncCompleted(): \DateTimeImmutable | null
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
}
