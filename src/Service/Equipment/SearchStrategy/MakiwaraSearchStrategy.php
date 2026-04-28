<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\MakiwaraRepository;
use Doctrine\ORM\QueryBuilder;

final class MakiwaraSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly MakiwaraRepository $makiwaraRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->makiwaraRepository->createQueryBuilder('m')
            ->leftJoin('m.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('m.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('m.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf('LOWER(%s.material)', $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::MAKIWARA;
    }
}
