<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Makiwara;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Makiwara>
 */
class MakiwaraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Makiwara::class);
    }
}
