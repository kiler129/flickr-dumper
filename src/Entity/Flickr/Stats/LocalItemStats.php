<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Stats;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class LocalItemStats
{
    #[ORM\Column]
    public int|null $views = 0;

    #[ORM\Column]
    public int|null $upVotes = 0;

    #[ORM\Column]
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
