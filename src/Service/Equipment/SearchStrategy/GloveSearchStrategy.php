<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\GloveRepository;
use Doctrine\ORM\QueryBuilder;

final class GloveSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly GloveRepository $gloveRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->gloveRepository->createQueryBuilder('g')
            ->leftJoin('g.owner_club', 'owner')->addSelect('owner')
            ->leftJoin('g.borrower_club', 'borrower')->addSelect('borrower')
            ->orderBy('g.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like("CONCAT($alias.nb_fingers, '')", ':term'),
                $queryBuilder->expr()->like("CONCAT($alias.size, '')", ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return EquipmentType::GLOVE;
    }
}
