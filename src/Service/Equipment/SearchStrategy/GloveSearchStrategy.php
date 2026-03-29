<?php

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use App\Repository\GloveRepository;
use Doctrine\ORM\QueryBuilder;

class GloveSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly GloveRepository $repository,
    ) {
    }

    public function buildQuery(string $searchTerm = '', string $status = 'all'): QueryBuilder
    {
        $qb = $this->repository->createQueryBuilder('g')
            ->leftJoin('g.owner_club', 'owner')->addSelect('owner')
            ->leftJoin('g.borrower_club', 'borrower')->addSelect('borrower')
            ->orderBy('g.id', 'DESC');

        if ('available' === $status) {
            $qb->andWhere('g.borrower_club IS NULL');
        } elseif ('loaned' === $status) {
            $qb->andWhere('g.borrower_club IS NOT NULL');
        }

        if ('' !== $searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('CONCAT(g.nb_fingers, \'\')', ':term'),
                    $qb->expr()->like('CONCAT(g.size, \'\')', ':term'),
                    $qb->expr()->like('LOWER(owner.name)', ':term'),
                    $qb->expr()->like('LOWER(borrower.name)', ':term')
                )
            )->setParameter('term', $searchTerm);
        }

        return $qb;
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return EquipmentType::GLOVE;
    }
}
