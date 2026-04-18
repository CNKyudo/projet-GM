<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Entity\Equipment;
use App\Enum\EquipmentType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class DefaultSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e', 'owner', 'borrower')
            ->from(Equipment::class, 'e')
            ->leftJoin('e.owner_club', 'owner')
            ->leftJoin('e.borrower_club', 'borrower')
            ->orderBy('e.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.id, '')", $alias), ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return null;
    }
}
