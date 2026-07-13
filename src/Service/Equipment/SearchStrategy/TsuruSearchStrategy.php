<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\TsuruRepository;
use Doctrine\ORM\QueryBuilder;

final class TsuruSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly TsuruRepository $tsuruRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->tsuruRepository->createQueryBuilder('t')
            ->leftJoin('t.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('t.borrowerClub', 'borrower')->addSelect('borrower')
            ->leftJoin('t.ownerRegion', 'ownerRegion')->addSelect('ownerRegion')
            ->orderBy('t.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf('LOWER(%s.tsuru_length)', $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.strength_min, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.strength_max, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.quantity, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::TSURU;
    }
}
