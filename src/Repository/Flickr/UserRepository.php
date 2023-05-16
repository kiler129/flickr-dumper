<?php

namespace App\Repository\Flickr;

use App\Entity\Flickr\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
