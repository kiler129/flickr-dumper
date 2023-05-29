<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photo;
use App\Exception\InvalidArgumentException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 *
 * @method Photo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Photo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Photo[]    findAll()
 * @method Photo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhotoRepository extends ServiceEntityRepository
{
    use PhotoFilterAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function save(Photo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Photo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Photo>
     */
    public function findLocked(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status.writeLockedAt IS NOT NULL')
            ->getQuery()
            ->getResult()
        ;
    }

    public function createArbitraryFiltered(array $filters, string $sortField, string $sortDir): QueryBuilder
    {
        $classMeta = $this->getClassMetadata();
        $qb = $this->createQueryBuilder('photo');
        return $this->createFilteredPhotos($classMeta, $qb, $filters, $sortField, $sortDir);

        //foreach ($filters as $field => $value) {
        //    if (!$classMeta->hasField($field)) {
        //        throw new InvalidArgumentException(
        //            \sprintf(
        //                'Field "%s" used for filtering does not exist in entity "%s"',
        //                $field,
        //                $this->getClassName()
        //            )
        //        );
        //    }
        //
        //    if (\is_string($value) && \str_contains($value, '%')) {
        //        $qb->expr()->andX($qb->expr()->like('photo.' . $field, ':' . $field));
        //        $qb->setParameter(':' . $field, $value);
        //    }
        //}
        //
        //if (!$classMeta->hasField($sortField)) {
        //    throw new InvalidArgumentException(
        //        \sprintf(
        //            'Field "%s" used for sorting does not exist in entity "%s"',
        //            $sortField,
        //            $this->getClassName()
        //        )
        //    );
        //}
        //
        //$order = \strtoupper($sortDir);
        //if ($order !== 'ASC' && $order !== 'DESC') {
        //    throw new InvalidArgumentException(
        //        \sprintf(
        //            'Invalid sort direction "%s" - it should be either ASC or DESC',
        //            $sortDir,
        //        )
        //    );
        //}
        //
        //$qb->orderBy('photo.' . $sortField, $order);
        //
        //return $qb;
    }
}
