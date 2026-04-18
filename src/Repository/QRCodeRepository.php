<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Equipment;
use App\Entity\QRCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QRCode>
 */
class QRCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QRCode::class);
    }

    public function findByUuid(string $uuid): ?QRCode
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByEquipment(Equipment $equipment): ?QRCode
    {
        return $this->findOneBy(['equipment' => $equipment]);
    }
}
