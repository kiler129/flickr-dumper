<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Stats;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class RemoteStats
{
    #[ORM\Column(nullable: true)]
    public int|null $views = null;

    #[ORM\Column(nullable: true)]
    public int|null $favorites = null;

    #[ORM\Column(nullable: true)]
    public int|null $comments = null;
}
