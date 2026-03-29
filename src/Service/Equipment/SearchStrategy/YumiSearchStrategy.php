<?php

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\YumiRepository;
use Doctrine\ORM\QueryBuilder;

class YumiSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly YumiRepository $repository,
    ) {
    }

    public function buildQuery(string $searchTerm = '', string $status = 'all'): QueryBuilder
    {
        $qb = $this->repository->createQueryBuilder('y')
            ->leftJoin('y.owner_club', 'owner')->addSelect('owner')
            ->leftJoin('y.borrower_club', 'borrower')->addSelect('borrower')
            ->orderBy('y.id', 'DESC');

        if ('available' === $status) {
            $qb->andWhere('y.borrower_club IS NULL');
        } elseif ('loaned' === $status) {
            $qb->andWhere('y.borrower_club IS NOT NULL');
        }

        if ('' !== $searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(y.material)', ':term'),
                    $qb->expr()->like('CONCAT(y.strength, \'\')', ':term'),
                    $qb->expr()->like('LOWER(y.length)', ':term'),
                    $qb->expr()->like('LOWER(owner.name)', ':term'),
                    $qb->expr()->like('LOWER(borrower.name)', ':term')
                )
            )->setParameter('term', $searchTerm);
        }

        return $qb;
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return EquipmentType::YUMI;
    }
}
