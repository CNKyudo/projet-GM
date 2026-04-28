<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\SupportMakiwaraRepository;
use Doctrine\ORM\QueryBuilder;

final class SupportMakiwaraSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly SupportMakiwaraRepository $supportMakiwaraRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->supportMakiwaraRepository->createQueryBuilder('s')
            ->leftJoin('s.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('s.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('s.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.hauteur, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::SUPPORT_MAKIWARA;
    }
}
