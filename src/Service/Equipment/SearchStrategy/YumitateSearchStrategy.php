<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\YumitateRepository;
use Doctrine\ORM\QueryBuilder;

final class YumitateSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly YumitateRepository $yumitateRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->yumitateRepository->createQueryBuilder('yt')
            ->leftJoin('yt.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('yt.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('yt.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.nb_bows, '')", $alias), ':term'),
                $queryBuilder->expr()->like(sprintf('LOWER(%s.orientation)', $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::YUMITATE;
    }
}
