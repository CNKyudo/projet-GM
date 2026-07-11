<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\ShitagakeRepository;
use Doctrine\ORM\QueryBuilder;

final class ShitagakeSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly ShitagakeRepository $shitagakeRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->shitagakeRepository->createQueryBuilder('sg')
            ->leftJoin('sg.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('sg.borrowerClub', 'borrower')->addSelect('borrower')
            ->leftJoin('sg.ownerRegion', 'ownerRegion')->addSelect('ownerRegion')
            ->orderBy('sg.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.nb_fingers, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.size, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.material, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.quantity, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::SHITAGAKE;
    }
}
