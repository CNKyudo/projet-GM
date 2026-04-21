<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\YumiRepository;
use Doctrine\ORM\QueryBuilder;

final class YumiSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly YumiRepository $yumiRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->yumiRepository->createQueryBuilder('y')
            ->leftJoin('y.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('y.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('y.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf('LOWER(%s.material)', $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.strength, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf('LOWER(%s.yumiLength)', $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::YUMI;
    }
}
