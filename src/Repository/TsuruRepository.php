<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tsuru;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tsuru>
 */
class TsuruRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tsuru::class);
    }
}
