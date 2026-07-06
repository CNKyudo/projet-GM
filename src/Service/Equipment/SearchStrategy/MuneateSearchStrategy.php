<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\MuneateRepository;
use Doctrine\ORM\QueryBuilder;

final class MuneateSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly MuneateRepository $muneateRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->muneateRepository->createQueryBuilder('mu')
            ->leftJoin('mu.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('mu.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('mu.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
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
        return EquipmentType::MUNEATE;
    }
}
