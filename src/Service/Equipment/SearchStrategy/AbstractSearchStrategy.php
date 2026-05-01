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
     * @param Club[]|null $restrictToClubs
     * @param Club[]|null $allowedClubsAvailableOnly
     * @param Region[]    $allowedRegions
     */
    final public function buildQuery(
        string $searchTerm = '',
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedClubsAvailableOnly = [],
        array $allowedRegions = [],
        bool $onlyAvailableRegional = false,
        bool $includeAllAvailableRegional = false,
        bool $includeNational = false,
    ): QueryBuilder {
        $queryBuilder = $this->createBaseQueryBuilder();
        $alias = $queryBuilder->getRootAliases()[0];

        $this->applyStatusFilter($queryBuilder, $alias, $status);
        $this->applyOwnershipFilter(
            $queryBuilder,
            $alias,
            $restrictToClubs,
            $allowedClubsAvailableOnly,
            $allowedRegions,
            $onlyAvailableRegional,
            $includeAllAvailableRegional,
            $includeNational,
        );

        if ('' !== $searchTerm) {
            $this->applySpecificSearchConditions($queryBuilder, $alias, $searchTerm);
        }

        return $queryBuilder;
    }

    /**
     * Applique le filtre de statut global (available / loaned) selon borrowerClub et borrowerMember.
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
     * @param Club[]|null $restrictToClubs             null = aucune restriction (admin/CN) ;
     *                                                 [] = aucun club ; [Club...] = filtre tous statuts
     * @param Club[]|null $allowedClubsAvailableOnly   null = tous clubs disponibles seulement ;
     *                                                 [] = aucun ; [Club...] = ces clubs disponibles seulement
     * @param Region[]    $allowedRegions              [] = aucune ; [Region...] = ces régions (statut selon $onlyAvailableRegional)
     * @param bool        $onlyAvailableRegional       true = $allowedRegions filtrés sur disponibles seulement
     * @param bool        $includeAllAvailableRegional true = ajoute tous les REGIONAL disponibles (toutes régions)
     * @param bool        $includeNational             true = inclure les équipements NATIONAL
     */
    protected function applyOwnershipFilter(
        QueryBuilder $queryBuilder,
        string $alias,
        ?array $restrictToClubs,
        ?array $allowedClubsAvailableOnly,
        array $allowedRegions,
        bool $onlyAvailableRegional,
        bool $includeAllAvailableRegional,
        bool $includeNational,
    ): void {
        // null = aucune restriction (admin / CN) → on n'ajoute aucune clause WHERE
        if (null === $restrictToClubs) {
            return;
        }

        $orParts = [];
        $available = sprintf('%s.borrowerClub IS NULL AND %s.borrowerMember IS NULL', $alias, $alias);

        // --- Niveau CLUB : clubs avec accès tous statuts ---
        if ([] !== $restrictToClubs) {
            $queryBuilder->setParameter('allowedClubs', $restrictToClubs);
            $queryBuilder->setParameter('levelClub', EquipmentLevel::CLUB);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelClub'),
                $queryBuilder->expr()->in($alias.'.ownerClub', ':allowedClubs')
            );
        }

        // --- Niveau CLUB : clubs visibles uniquement si disponibles ---
        if (null === $allowedClubsAvailableOnly) {
            // null = tous les clubs disponibles (sans restriction de club)
            $queryBuilder->setParameter('levelClubAvail', EquipmentLevel::CLUB);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelClubAvail'),
                $queryBuilder->expr()->andX($available)
            );
        } elseif ([] !== $allowedClubsAvailableOnly) {
            $queryBuilder->setParameter('allowedClubsAvail', $allowedClubsAvailableOnly);
            $queryBuilder->setParameter('levelClubAvailList', EquipmentLevel::CLUB);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelClubAvailList'),
                $queryBuilder->expr()->in($alias.'.ownerClub', ':allowedClubsAvail'),
                $queryBuilder->expr()->andX($available)
            );
        }

        // --- Niveau REGIONAL : régions spécifiques (tous statuts ou disponibles seulement) ---
        if ([] !== $allowedRegions) {
            $queryBuilder->setParameter('allowedRegions', $allowedRegions);
            $queryBuilder->setParameter('levelRegional', EquipmentLevel::REGIONAL);
            if ($onlyAvailableRegional) {
                $orParts[] = $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelRegional'),
                    $queryBuilder->expr()->in($alias.'.ownerRegion', ':allowedRegions'),
                    $queryBuilder->expr()->andX($available)
                );
            } else {
                $orParts[] = $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelRegional'),
                    $queryBuilder->expr()->in($alias.'.ownerRegion', ':allowedRegions')
                );
            }
        }

        // --- Niveau REGIONAL : toutes régions disponibles ---
        if ($includeAllAvailableRegional) {
            $queryBuilder->setParameter('levelRegionalAll', EquipmentLevel::REGIONAL);
            $orParts[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($alias.'.equipmentLevel', ':levelRegionalAll'),
                $queryBuilder->expr()->andX($available)
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
