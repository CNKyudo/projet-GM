<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Region;
use App\Entity\Equipment;
use App\Enum\EquipmentType;
use App\Service\Equipment\SearchStrategy\SearchStrategyInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @extends ServiceEntityRepository<Equipment>
 */
class EquipmentRepository extends ServiceEntityRepository
{
    /** @var array<string, SearchStrategyInterface> */
    private array $strategyMap = [];

    /**
     * @param iterable<SearchStrategyInterface> $strategies
     */
    public function __construct(
        ManagerRegistry $registry,
        #[AutowireIterator(SearchStrategyInterface::class)]
        iterable $strategies,
    ) {
        parent::__construct($registry, Equipment::class);
        foreach ($strategies as $strategy) {
            $this->strategyMap[$strategy->getEquipmentType()->value ?? ''] = $strategy;
        }
    }

    /**
     * Retourne un QueryBuilder construit par la bonne stratégie.
     *
     * @param string             $query           Terme de recherche
     * @param EquipmentType|null $equipmentType   Type d'équipement (null, YUMI, GLOVE)
     * @param string             $status          Statut ('all', 'available', 'loaned')
     * @param array<Club>|null   $restrictToClubs null = pas de restriction, [] = aucun résultat, [Club...] = filtre CLUB
     * @param array<Region>|null $allowedRegions  null = pas de restriction REGIONAL, [] = aucun REGIONAL, [Region...] = filtre
     * @param bool               $includeNational true = inclure les équipements NATIONAL
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function findBySearchStrategy(
        string $query = '',
        ?EquipmentType $equipmentType = null,
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedRegions = [],
        bool $includeNational = false,
    ): QueryBuilder {
        $term = '' !== $query ? '%'.mb_strtolower($query).'%' : '';
        $searchStrategy = $this->getStrategyForType($equipmentType);

        return $searchStrategy->buildQuery($term, $status, $restrictToClubs, $allowedRegions, $includeNational);
    }

    /**
     * Retourne la stratégie de recherche appropriée selon le type d'équipement.
     */
    private function getStrategyForType(?EquipmentType $equipmentType): SearchStrategyInterface
    {
        if (isset($this->strategyMap[$equipmentType->value ?? ''])) {
            return $this->strategyMap[$equipmentType->value ?? ''];
        }

        // On retourne la stratégie par défaut si le type d'équipement n'a aucune stratégie dédiée
        return $this->strategyMap[''];
    }
}
