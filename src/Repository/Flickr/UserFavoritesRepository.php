<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\UserFavorites;
use App\Repository\CollectionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFavorites::class);
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
