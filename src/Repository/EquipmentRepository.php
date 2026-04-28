<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Region;
use App\Entity\Equipment;
use App\Enum\EquipmentType;
use App\Service\Equipment\SearchStrategy\DefaultSearchStrategy;
use App\Service\Equipment\SearchStrategy\GloveSearchStrategy;
use App\Service\Equipment\SearchStrategy\MakiwaraSearchStrategy;
use App\Service\Equipment\SearchStrategy\SearchStrategyInterface;
use App\Service\Equipment\SearchStrategy\SupportMakiwaraSearchStrategy;
use App\Service\Equipment\SearchStrategy\YumiSearchStrategy;
use App\Service\Equipment\SearchStrategy\YumitateSearchStrategy;
use App\Service\Equipment\SearchStrategy\YatateSearchStrategy;
use App\Service\Equipment\SearchStrategy\MakuSearchStrategy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipment>
 */
class EquipmentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DefaultSearchStrategy $defaultSearchStrategy,
        private readonly YumiSearchStrategy $yumiSearchStrategy,
        private readonly GloveSearchStrategy $gloveSearchStrategy,
        private readonly MakiwaraSearchStrategy $makiwaraSearchStrategy,
        private readonly SupportMakiwaraSearchStrategy $supportMakiwaraSearchStrategy,
        private readonly YumitateSearchStrategy $yumitateSearchStrategy,
        private readonly YatateSearchStrategy $yatateSearchStrategy,
        private readonly MakuSearchStrategy $makuSearchStrategy,
    ) {
        parent::__construct($registry, Equipment::class);
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
        return match ($equipmentType) {
            EquipmentType::YUMI => $this->yumiSearchStrategy,
            EquipmentType::GLOVE => $this->gloveSearchStrategy,
            EquipmentType::MAKIWARA => $this->makiwaraSearchStrategy,
            EquipmentType::SUPPORT_MAKIWARA => $this->supportMakiwaraSearchStrategy,
            EquipmentType::YUMITATE => $this->yumitateSearchStrategy,
            EquipmentType::YATATE => $this->yatateSearchStrategy,
            EquipmentType::MAKU => $this->makuSearchStrategy,
            default => $this->defaultSearchStrategy,
        };
    }
}
