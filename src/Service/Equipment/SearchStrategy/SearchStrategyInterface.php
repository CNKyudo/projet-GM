<?php

namespace App\Service\Equipment\SearchStrategy;

use App\Enum\EquipmentType;
use Doctrine\ORM\QueryBuilder;

interface SearchStrategyInterface
{
    /**
     * Construit et retourne un QueryBuilder complètement préparé.
     *
     * @param string $searchTerm Le terme de recherche (lowercase avec wildcards)
     * @param string $status     Statut ('all', 'available', 'loaned')
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function buildQuery(string $searchTerm = '', string $status = 'all'): QueryBuilder;

    /**
     * Retourne le type d'équipement géré par cette stratégie.
     */
    public function getEquipmentType(): ?EquipmentType;
}
