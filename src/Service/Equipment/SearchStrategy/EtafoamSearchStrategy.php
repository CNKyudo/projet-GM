<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\EtafoamRepository;
use Doctrine\ORM\QueryBuilder;

final class EtafoamSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly EtafoamRepository $etafoamRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->etafoamRepository->createQueryBuilder('ef')
            ->leftJoin('ef.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('ef.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('ef.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.length, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.width, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.thickness, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::ETAFOAM;
    }
}
