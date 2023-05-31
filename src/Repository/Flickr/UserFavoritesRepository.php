<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\UserFavorites;
use App\Repository\CollectionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserFavorites>
 *
 * @method UserFavorites|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserFavorites|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserFavorites[]    findAll()
 * @method UserFavorites[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserFavoritesRepository extends PhotoCollectionRepository
{
    use PhotoFilterAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFavorites::class);
    }

    public function createForAllPhotosInFavorites(string $ownerId, array $photoFilters, string $sortField, string $sortDir): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('photo')
            ->from(Photo::class, 'photo')
            ->join('photo.userFavorites', 'userFavorites', Expr\Join::WITH, 'userFavorites.owner = :ownerId')
            ->setParameter('ownerId', $ownerId)
        ;

        return $this->createFilteredPhotos(
            $this->getEntityManager()->getClassMetadata(Photo::class),
            $qb,
            $photoFilters,
            $sortField,
            $sortDir
        );
    }

    public function save(UserFavorites $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserFavorites $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
