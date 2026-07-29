<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Azuchi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Azuchi>
 */
class AzuchiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Azuchi::class);
    }
}
