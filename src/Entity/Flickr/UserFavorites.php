<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use App\Repository\Flickr\UserFavoritesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserFavoritesRepository::class)]
class UserFavorites implements PhotoCollection, Syncable, UserOwnedEntity
{
    use PhotoCollectionFragment;
    use SyncableFragment;

    #[ORM\Id]
    //#[ORM\OneToOne(inversedBy: 'favorites', cascade: ['persist', 'remove'])]
    //#[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'favorites')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'nsid')]
    private User $owner;

    #[ORM\ManyToMany(targetEntity: Photo::class, fetch: 'EXTRA_LAZY', inversedBy: 'userFavorites')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'owner_id', name: 'owner_id')]
    private Collection $photos;

    public function __construct(User $owner, ?\DateTimeInterface $retrieved = null)
    {
        $this->setOwner($owner);
        $this->setDateLastRetrieved($retrieved ?? new \DateTimeImmutable());
        $this->photos = new ArrayCollection();
        $this->status = new CollectionStatus();
    }

    public function getUserReadableId(): string
    {
        return 'Favorite of user NSID=' . $this->owner->getNsid();
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    private function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public static function ownerOwnsPhotos(): bool
    {
        return false;
    }
}
