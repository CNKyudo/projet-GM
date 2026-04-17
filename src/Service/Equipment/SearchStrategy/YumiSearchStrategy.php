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
            ->leftJoin('y.owner_club', 'owner')->addSelect('owner')
            ->leftJoin('y.borrower_club', 'borrower')->addSelect('borrower')
            ->orderBy('y.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like("LOWER($alias.material)", ':term'),
                $queryBuilder->expr()->like("CONCAT($alias.strength, '')", ':term'),
                $queryBuilder->expr()->like("LOWER($alias.yumiLength)", ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return EquipmentType::YUMI;
    }
}
