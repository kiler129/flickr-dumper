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

    public function createForAllDisplayable(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('_ps');
        $qb
            ->select('_ps as photoset')
            ->addSelect('_u hidden')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' _p ' .
                        ' JOIN _p.photosets _pps' .
                        ' WHERE _pps.id = _ps.id' .
                        ') as photo_count')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' _pv ' .
                        ' JOIN _pv.photosets _ppps' .
                        ' WHERE _ppps.id = _ps.id AND ' .
                        '       _pv.status.deleted = false AND ' .
                        '       _pv.localStats.upVotes = 0 AND ' .
                        '       _pv.localStats.downVotes = 0 ' .
                        ') as photos_without_votes')
            ->join('_ps.owner', '_u', Expr\Join::WITH, '_ps.owner = _u.nsid')
            ->where(
                    $qb->expr()->eq('_ps.status.deleted', 'false'),
            )
            ->addOrderBy('_ps.dateCreated', 'DESC')
            ->addOrderBy('_ps.dateLastUpdated', 'DESC')
            ->addOrderBy('_ps.dateLastRetrieved', 'DESC')
        ;

        return $qb;
    }

    public function createForAllDisplayableForUser(string $ownerId): QueryBuilder
    {
        $qb = $this->createForAllDisplayable();
        $qb->andWhere('_ps.owner = :ownerId')
            ->setParameter(':ownerId', $ownerId);

        return $qb;
    }


    public function createForAllPhotosInAlbum(int $photosetId, array $photoFilters, string $sortField, string $sortDir): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
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
