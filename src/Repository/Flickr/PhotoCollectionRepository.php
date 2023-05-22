<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\PhotoCollection;
use App\Repository\CollectionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class PhotoCollectionRepository extends ServiceEntityRepository implements CollectionRepository
{
    /**
     * @return list<PhotoCollection>
     */
    public function findLocked(): array
    {
        return $this->createQueryBuilder('p')
                    ->andWhere('p.status.writeLockedAt IS NOT NULL')
                    ->getQuery()
                    ->getResult()
            ;
    }
}
