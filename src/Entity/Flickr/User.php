<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use App\Exception\LogicException;
use App\Repository\Flickr\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private string $nsid; //full NSID from Flickr

    /**
     * @var string This is non-optional display name of the user, e.g. "SpaceX Photos"
     */
    #[ORM\Column(length: 255)]
    private string $userName;

    /**
     * @var string|null This is an optional "nickname", e.g. "spacex"
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $screenName;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Photo::class)]
    private Collection $photos;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Photoset::class)]
    private Collection $photosets;

    #[ORM\OneToOne(mappedBy: 'owner', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(referencedColumnName: 'owner_id')]
    private ?UserFavorites $favorites = null;

    public function __construct(string $nsid, string $userName, ?string $screenName = null)
    {
        $this->nsid = $nsid;
        $this->userName = $userName;
        $this->screenName = $screenName;

        $this->photos = new ArrayCollection();
        $this->photosets = new ArrayCollection();
    }

    public function getNsid(): string
    {
        return $this->nsid;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): self
    {
        $this->userName = $userName;

        return $this;
    }

    public function getScreenName(): ?string
    {
        return $this->screenName;
    }

    public function setScreenName(?string $screenName): self
    {
        $this->screenName = $screenName;

        return $this;
    }

    /**
     * @return Collection<int, Photo>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setOwner($this);
        }

        return $this;
    }

    public function removePhoto(Photo $photo): self
    {
        if ($this->photos->removeElement($photo)) {
            // set the owning side to null (unless already changed)
            if ($photo->getOwner() === $this) {
                $photo->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Photoset>
     */
    public function getPhotosets(): Collection
    {
        return $this->photosets;
    }

    public function getFavorites(): ?UserFavorites
    {
        return $this->favorites;
    }

    public function setFavorites(UserFavorites $favorites): self
    {
        if ($favorites->getOwner() !== $this) {
            throw new LogicException(
                \sprintf(
                    'You cannot assign favorites of user ID=%s to user ID=%s',
                    $favorites->getOwner()->getNsid(),
                    $this->getNsid()
                )
            );
        }

        $this->favorites = $favorites;

        return $this;
    }

    public function getDisplayableShortName(): string
    {
        if (isset($this->screenName)) {
            return '@' . $this->screenName;
        }

        if (isset($this->userName)) {
            return $this->userName;
        }

        return $this->nsid;
    }
}
