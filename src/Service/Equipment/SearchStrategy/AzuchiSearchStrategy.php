<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\AzuchiRepository;
use Doctrine\ORM\QueryBuilder;

final class AzuchiSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly AzuchiRepository $azuchiRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->azuchiRepository->createQueryBuilder('az')
            ->leftJoin('az.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('az.borrowerClub', 'borrower')->addSelect('borrower')
            ->leftJoin('az.ownerRegion', 'ownerRegion')->addSelect('ownerRegion')
            ->orderBy('az.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.equipment_length, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.width, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.thickness, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::AZUCHI;
    }
}
