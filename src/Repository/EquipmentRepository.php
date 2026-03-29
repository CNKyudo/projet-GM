<?php

namespace App\Repository;

use App\Entity\Equipment;
use App\Enum\EquipmentType;
use App\Service\Equipment\SearchStrategy\DefaultSearchStrategy;
use App\Service\Equipment\SearchStrategy\GloveSearchStrategy;
use App\Service\Equipment\SearchStrategy\SearchStrategyInterface;
use App\Service\Equipment\SearchStrategy\YumiSearchStrategy;
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
        private readonly DefaultSearchStrategy $defaultStrategy,
        private readonly YumiSearchStrategy $yumiStrategy,
        private readonly GloveSearchStrategy $gloveStrategy,
    ) {
        parent::__construct($registry, Equipment::class);
    }

    /**
     * Retourne un QueryBuilder construit par la bonne stratégie.
     *
     * @param string             $query         Terme de recherche
     * @param EquipmentType|null $equipmentType Type d'équipement (null, YUMI, GLOVE)
     * @param string             $status        Statut ('all', 'available', 'loaned')
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function findBySearchStrategy(
        string $query = '',
        ?EquipmentType $equipmentType = null,
        string $status = 'all',
    ): QueryBuilder {
        $term = '' !== $query ? '%'.mb_strtolower($query).'%' : '';
        $strategy = $this->getStrategyForType($equipmentType);

        return $strategy->buildQuery($term, $status);
    }

    /**
     * Retourne la stratégie de recherche appropriée selon le type d'équipement.
     */
    private function getStrategyForType(?EquipmentType $equipmentType): SearchStrategyInterface
    {
        return match ($equipmentType) {
            EquipmentType::YUMI => $this->yumiStrategy,
            EquipmentType::GLOVE => $this->gloveStrategy,
            default => $this->defaultStrategy,
        };
    }
}
