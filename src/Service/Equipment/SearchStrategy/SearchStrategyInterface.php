<?php

declare(strict_types=1);

namespace App\Service\Equipment\SearchStrategy;

use App\Entity\Club;
use App\Entity\Region;
use App\Enum\EquipmentType;
use Doctrine\ORM\QueryBuilder;

interface SearchStrategyInterface
{
    /**
     * Construit et retourne un QueryBuilder complètement préparé.
     *
     * @param string        $searchTerm      Le terme de recherche (lowercase avec wildcards)
     * @param string        $status          Statut ('all', 'available', 'loaned')
     * @param Club[]|null   $restrictToClubs null = pas de restriction (admin/CTK/CN) ;
     *                                       [] = aucun résultat (membre sans club) ;
     *                                       [Club, ...] = filtre sur ces clubs uniquement (niveau CLUB)
     * @param Region[]|null $allowedRegions  Régions dont l'utilisateur peut voir les équipements REGIONAL.
     *                                       null = pas de restriction sur le niveau REGIONAL ;
     *                                       [] = aucun équipement REGIONAL visible ;
     *                                       [Region, ...] = filtre sur ces régions
     * @param bool          $includeNational true = inclure les équipements NATIONAL dans les résultats
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function buildQuery(
        string $searchTerm = '',
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedRegions = [],
        bool $includeNational = false,
    ): QueryBuilder;

    /**
     * Retourne le type d'équipement géré par cette stratégie.
     */
    public function getEquipmentType(): ?EquipmentType;
}
