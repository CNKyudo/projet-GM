<?php

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class DefaultSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function buildQuery(string $searchTerm = '', string $status = 'all'): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e', 'owner', 'borrower')
            ->from(\App\Entity\Equipment::class, 'e')
            ->leftJoin('e.owner_club', 'owner')
            ->leftJoin('e.borrower_club', 'borrower')
            ->orderBy('e.id', 'DESC');

        if ('available' === $status) {
            $qb->andWhere('e.borrower_club IS NULL');
        } elseif ('loaned' === $status) {
            $qb->andWhere('e.borrower_club IS NOT NULL');
        }

        if ('' !== $searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(owner.name)', ':term'),
                    $qb->expr()->like('LOWER(borrower.name)', ':term'),
                    $qb->expr()->like('CONCAT(e.id, \'\')', ':term')
                )
            )->setParameter('term', $searchTerm);
        }

        return $qb;
    }

    public function getEquipmentType(): ?EquipmentType
    {
        return null;
    }
}
