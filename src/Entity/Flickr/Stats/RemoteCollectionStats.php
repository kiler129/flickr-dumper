<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Stats;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class RemoteCollectionStats extends RemoteStats
{
    #[ORM\Column(nullable: true)]
    public int|null $photos = null;

    #[ORM\Column(nullable: true)]
    public int|null $videos = null;
}
