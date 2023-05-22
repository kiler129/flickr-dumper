<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photoset;
use App\Repository\CollectionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photoset>
 *
 * @method Photoset|null find($id, $lockMode = null, $lockVersion = null)
 * @method Photoset|null findOneBy(array $criteria, array $orderBy = null)
 * @method Photoset[]    findAll()
 * @method Photoset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhotosetRepository extends PhotoCollectionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photoset::class);
    }

    public function save(Photoset $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Photoset $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
