<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\Photoset;
use App\Entity\Flickr\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function createAllWithProperties(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('_u');

        $qb
            ->select('_u as user')
            ->addSelect('_uf hidden')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' photo ' .
                        ' WHERE photo.owner = _u AND ' .
                        '       photo.status.deleted = false) as photo_count')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photoset::class . ' ps ' .
                        ' WHERE ps.owner = _u AND ' .
                        '       ps.status.deleted = false' .
                        ') as photoset_count')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' ufp ' .
                        ' JOIN ufp.userFavorites uf ' .
                        ' WHERE uf.owner = _u' .
                        ') as faves_count')
            ->addSelect('(SELECT COUNT(1) FROM ' . Photo::class . ' ufpv ' .
                        ' JOIN ufpv.userFavorites ufv ' .
                        ' WHERE ufv.owner = _u AND ' .
                        '       ufpv.status.deleted = false AND '.
                        '       ufpv.localStats.upVotes = 0 AND '.
                        '       ufpv.localStats.downVotes = 0 ' .
                        ') as faves_photos_without_votes')
            ->leftJoin('_u.favorites', '_uf', Expr\Join::WITH, '_uf.owner = _u.nsid')
            ->addOrderBy('faves_photos_without_votes', 'DESC')
            ->addOrderBy('faves_count', 'DESC')
            ->addOrderBy('photo_count', 'DESC')
            ->addOrderBy('photoset_count', 'DESC')
        ;

        return $qb;
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Searches user by any unique identifier (NSID or screenname)
     */
    public function findOneByIdentifier(string $identifier): ?User
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('u')
            ->where('u.nsid = :identifier')
            ->orWhere('u.screenName = :identifier')
            ->setParameter('identifier', $identifier);

        return $qb->getQuery()->getOneOrNullResult();
    }


}
