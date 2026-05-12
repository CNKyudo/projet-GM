<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findPresidentOfClub(Club $club): ?User
    {
        return $this->createQueryBuilder('u')
            ->join('u.clubWhichImPresidentOf', 'c')
            ->where('c.id = :id')
            ->setParameter('id', $club->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findEquipmentManagerOfClub(Club $club): ?User
    {
        return $this->createQueryBuilder('u')
            ->join('u.clubWhereImEquipmentManager', 'c')
            ->where('c.id = :id')
            ->setParameter('id', $club->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
