<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\MakuRepository;
use Doctrine\ORM\QueryBuilder;

final class MakuSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly MakuRepository $makuRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->makuRepository->createQueryBuilder('mk')
            ->leftJoin('mk.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('mk.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('mk.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.length, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.height, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf('LOWER(%s.material)', $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.weight, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf('LOWER(%s.accroche)', $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::MAKU;
    }
}
