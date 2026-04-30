<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Etafoam;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Etafoam>
 */
class EtafoamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etafoam::class);
    }
}
