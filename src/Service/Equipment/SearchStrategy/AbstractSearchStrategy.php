<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Entity\Club;
use App\Entity\Region;
use App\Enum\EquipmentLevel;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractSearchStrategy implements SearchStrategyInterface
{
    /**
     * Retourne un QueryBuilder initialisé avec les jointures nécessaires
     * (ownerClub, borrowerClub, orderBy) mais sans aucun filtre.
     */
    abstract protected function createBaseQueryBuilder(): QueryBuilder;

    /**
     * Ajoute les conditions de recherche spécifiques au type d'équipement.
     * N'est appelé que si $searchTerm est non vide.
     */
    abstract protected function applySpecificSearchConditions(
        QueryBuilder $queryBuilder,
        string $alias,
        string $searchTerm,
    ): void;

    /**
     * @param Club[]|null   $restrictToClubs
     * @param Region[]|null $allowedRegions
     */
    final public function buildQuery(
        string $searchTerm = '',
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedRegions = [],
        bool $includeNational = false,
    ): QueryBuilder {
        $queryBuilder = $this->createBaseQueryBuilder();
        $alias = $queryBuilder->getRootAliases()[0];

        $this->applyStatusFilter($queryBuilder, $alias, $status);
        $this->applyOwnershipFilter($queryBuilder, $alias, $restrictToClubs, $allowedRegions, $includeNational);

        if ('' !== $searchTerm) {
            $this->applySpecificSearchConditions($queryBuilder, $alias, $searchTerm);
        }

        return $queryBuilder;
    }

    /**
     * Applique le filtre de statut (available / loaned) selon borrowerClub et borrowerMember.
     */
    protected function applyStatusFilter(QueryBuilder $queryBuilder, string $alias, string $status): void
    {
        if ('available' === $status) {
            $queryBuilder->andWhere(sprintf('%s.borrowerClub IS NULL AND %s.borrowerMember IS NULL', $alias, $alias));
        } elseif ('loaned' === $status) {
            $queryBuilder->andWhere(sprintf('%s.borrowerClub IS NOT NULL OR %s.borrowerMember IS NOT NULL', $alias, $alias));
        }
    }

    /**
     * Applique le filtre de visibilité selon les clubs, régions et niveau national autorisés.
     *
     * @param Club[]|null   $restrictToClubs null = pas de restriction ; [] = aucun résultat ; [Club...] = filtre
     * @param Region[]|null $allowedRegions  null = pas de restriction REGIONAL ; [] = aucun REGIONAL ; [Region...] = filtre
     * @param bool          $includeNational true = inclure les équipements NATIONAL
     */
    protected function applyOwnershipFilter(
        QueryBuilder $queryBuilder,
        string $alias,
        ?array $restrictToClubs,
        ?array $allowedRegions,
        bool $includeNational,
    ): void {
        // null = pas de restriction du tout (admin / CN) → on n'ajoute aucune clause
        if (null === $restrictToClubs) {
            return;
        }

        $orParts = [];

        // --- Niveau CLUB ---
        if ([] !== $restrictToClubs) {
            $queryBuilder->setParameter('allowedClubs', $restrictToClubs);
            $queryBuilder->setParameter('levelClub', EquipmentLevel::CLUB);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelClub'),
                $queryBuilder->expr()->in($alias.'.ownerClub', ':allowedClubs')
            );
        }

        // --- Niveau REGIONAL ---
        if (null !== $allowedRegions && [] !== $allowedRegions) {
            $queryBuilder->setParameter('allowedRegions', $allowedRegions);
            $queryBuilder->setParameter('levelRegional', EquipmentLevel::REGIONAL);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelRegional'),
                $queryBuilder->expr()->in($alias.'.ownerRegion', ':allowedRegions')
            );
        }

        // --- Niveau NATIONAL ---
        if ($includeNational) {
            $queryBuilder->setParameter('levelNational', EquipmentLevel::NATIONAL);
            $orParts[] = $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelNational');
        }

        if ([] !== $orParts) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$orParts));
        }
    }
}
