<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\YugakeRepository;
use Doctrine\ORM\QueryBuilder;

final class YugakeSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly YugakeRepository $yugakeRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->yugakeRepository->createQueryBuilder('g')
            ->leftJoin('g.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('g.borrowerClub', 'borrower')->addSelect('borrower')
            ->leftJoin('g.ownerRegion', 'ownerRegion')->addSelect('ownerRegion')
            ->orderBy('g.id', 'DESC');
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
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::YUGAKE;
    }
}
