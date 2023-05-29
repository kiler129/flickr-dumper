<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use Doctrine\ORM\Mapping as ORM;

//@todo change to non-null after script finishes running (changing db while running rotfl)
//Also put 0 in every field that is null ;D
#[ORM\Embeddable]
class PhotoRanking
{
    #[ORM\Column(nullable: true)]
    public int|null $views = 0;

    #[ORM\Column(nullable: true)]
    public int|null $upVotes = 0;

    #[ORM\Column(nullable: true)]
    public int|null $downVotes = 0;

    public function triggerView(): void
    {
        ++$this->views;
    }

    public function upVote(): void
    {
        ++$this->upVotes;
    }

    public function downVote(): void
    {
        ++$this->downVotes;
    }

    public function voteRanking(): int
    {
        return $this->upVotes - $this->downVotes;
    }
}
