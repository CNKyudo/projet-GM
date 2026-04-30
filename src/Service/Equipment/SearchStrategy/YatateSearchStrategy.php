<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\YatateRepository;
use Doctrine\ORM\QueryBuilder;

final class YatateSearchStrategy extends AbstractSearchStrategy
{
    public function __construct(
        private readonly YatateRepository $yatateRepository,
    ) {
    }

    protected function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->yatateRepository->createQueryBuilder('ya')
            ->leftJoin('ya.ownerClub', 'owner')->addSelect('owner')
            ->leftJoin('ya.borrowerClub', 'borrower')->addSelect('borrower')
            ->orderBy('ya.id', 'DESC');
    }

    protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(sprintf("CONCAT(%s.nb_arrows, '')", $alias), ':term'),
                $queryBuilder->expr()->like('LOWER(owner.name)', ':term'),
                $queryBuilder->expr()->like('LOWER(borrower.name)', ':term')
            )
        )->setParameter('term', $searchTerm);
    }

    public function getEquipmentType(): EquipmentType
    {
        return EquipmentType::YATATE;
    }
}
