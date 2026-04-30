<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Entity\Club;
use App\Entity\Region;
use App\Enum\EquipmentType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SearchStrategyInterface
{
    /**
     * Construit et retourne un QueryBuilder complètement préparé.
     *
     * @param string      $searchTerm                  Terme de recherche (lowercase avec wildcards)
     * @param string      $status                      Statut global ('all', 'available', 'loaned')
     * @param Club[]|null $restrictToClubs             null = aucune restriction (admin/CN) ;
     *                                                 [] = aucun club ; [Club, ...] = filtre sur ces clubs (tous statuts)
     * @param Club[]|null $allowedClubsAvailableOnly   null = tous les clubs disponibles seulement ;
     *                                                 [] = aucun ; [Club, ...] = ces clubs disponibles seulement
     * @param Region[]    $allowedRegions              Régions dont l'utilisateur peut voir les équipements REGIONAL.
     *                                                 [] = aucune région ; [Region, ...] = filtre sur ces régions
     * @param bool        $onlyAvailableRegional       true = les équipements REGIONAL de $allowedRegions
     *                                                 ne sont visibles que s'ils sont disponibles
     * @param bool        $includeAllAvailableRegional true = ajoute tous les équipements REGIONAL disponibles
     *                                                 (indépendamment de $allowedRegions)
     * @param bool        $includeNational             true = inclure les équipements NATIONAL dans les résultats
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function buildQuery(
        string $searchTerm = '',
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedClubsAvailableOnly = [],
        array $allowedRegions = [],
        bool $onlyAvailableRegional = false,
        bool $includeAllAvailableRegional = false,
        bool $includeNational = false,
    ): QueryBuilder;

    /**
     * Retourne le type d'équipement géré par cette stratégie.
     */
    public function getEquipmentType(): ?EquipmentType;
}
