<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\Photoset;
use App\Repository\CollectionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
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
    use PhotoFilterAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photoset::class);
    }

    public function findAllDisplayable(): iterable
    {
        $qb = $this->createQueryBuilder('photoset');
        $qb
            //->join('photoset.owner', 'owner')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('photoset.status.blacklisted', 'false'),
                    $qb->expr()->eq('photoset.status.deleted', 'false'),
                )
            );
        $q = $qb->getQuery();

        return $q->getResult();
    }

    public function createForAllPhotosInAlbum(int $photosetId, array $photoFilters, string $sortField, string $sortDir): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        //$qb
        //    ->select('photo')
        //    ->from(Photo::class, 'photo')
        //    ->join(Photoset::class, 'photoset', Expr\Join::WITH, 'photo MEMBER photoset.photos')
        //    ->where('photoset.id = :photosetId')
        //    ->setParameter('photosetId', $photosetId)
        //;
        //
        $qb
            ->select('photo')
            ->from(Photo::class, 'photo')
            ->join('photo.photosets', 'photoset', Expr\Join::WITH, 'photoset.id = :photosetId')
            ->setParameter('photosetId', $photosetId)
        ;


        return $this->createFilteredPhotos(
            $this->getEntityManager()->getClassMetadata(Photo::class),
            $qb,
            $photoFilters,
            $sortField,
            $sortDir
        );
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
