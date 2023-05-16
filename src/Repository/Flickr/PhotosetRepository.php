<?php

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
class PhotosetRepository extends ServiceEntityRepository implements CollectionRepository
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

//    /**
//     * @return Photoset[] Returns an array of Photoset objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Photoset
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
