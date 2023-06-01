<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Collection\Gallery;
use App\Entity\Flickr\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gallery>
 *
 * @method Gallery|null find($id, $lockMode = null, $lockVersion = null)
 * @method Gallery|null findOneBy(array $criteria, array $orderBy = null)
 * @method Gallery[]    findAll()
 * @method Gallery[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GalleryRepository extends PhotoCollectionRepository
{
    use PhotoFilterAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class);
    }

    public function createForAllDisplayable(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('_gal');
        $qb
            ->select('_gal as gallery')
            ->addSelect('_u hidden')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' _p ' .
                        ' JOIN _p.galleries _pps' .
                        ' WHERE _pps.id = _gal.id' .
                        ') as photo_count')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' _pv ' .
                        ' JOIN _pv.galleries _ppps' .
                        ' WHERE _ppps.id = _gal.id AND ' .
                        '       _pv.status.deleted = false AND ' .
                        '       _pv.localStats.upVotes = 0 AND ' .
                        '       _pv.localStats.downVotes = 0 ' .
                        ') as photos_without_votes')
            ->join('_gal.owner', '_u', Expr\Join::WITH, '_gal.owner = _u.nsid')
            ->where(
                    $qb->expr()->eq('_gal.status.deleted', 'false'),
            )
            ->addOrderBy('_gal.dateCreated', 'DESC')
            ->addOrderBy('_gal.dateLastUpdated', 'DESC')
            ->addOrderBy('_gal.dateLastRetrieved', 'DESC')
        ;
    
        return $qb;
    }

    public function createForAllDisplayableForUser(string $ownerId): QueryBuilder
    {
        $qb = $this->createForAllDisplayable();
        $qb->andWhere('_gal.owner = :ownerId')
            ->setParameter(':ownerId', $ownerId);

        return $qb;
    }

    public function createForAllPhotosInGallery(int $galleryId, array $photoFilters, string $sortField, string $sortDir): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('photo')
            ->from(Photo::class, 'photo')
            ->join('photo.galleries', 'gallery', Expr\Join::WITH, 'gallery.id = :galleryId')
            ->setParameter('galleryId', $galleryId)
        ;

        return $this->createFilteredPhotos(
            $this->getEntityManager()->getClassMetadata(Photo::class),
            $qb,
            $photoFilters,
            $sortField,
            $sortDir
        );
    }

    public function save(Gallery $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Gallery $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
