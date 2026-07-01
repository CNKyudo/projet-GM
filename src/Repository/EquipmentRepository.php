<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use App\Entity\ClubMember;
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
     * @param string             $query                       Terme de recherche
     * @param EquipmentType|null $equipmentType               Type d'équipement (null, YUMI, GAKE)
     * @param string             $status                      Statut global ('all', 'available', 'loaned')
     * @param array<Club>|null   $restrictToClubs             null = aucune restriction ; [] = aucun ; [Club...] = filtre tous statuts
     * @param array<Club>|null   $allowedClubsAvailableOnly   null = tous clubs disponibles ; [] = aucun ; [Club...] = filtre disponibles seulement
     * @param array<Region>      $allowedRegions              [] = aucune ; [Region...] = ces régions (statut selon $onlyAvailableRegional)
     * @param bool               $onlyAvailableRegional       true = $allowedRegions disponibles seulement
     * @param bool               $includeAllAvailableRegional true = tous les REGIONAL disponibles (toutes régions)
     * @param bool               $includeNational             true = inclure les équipements NATIONAL
     * @param bool               $filterByBorrower            true = filtrer par emprunteur (si $borrowerMember null, aucun résultat)
     * @param ClubMember|null    $borrowerMember              Le membre emprunteur (utilisé uniquement si $filterByBorrower = true)
     *
     * @return QueryBuilder Le QueryBuilder prêt à paginer
     */
    public function findBySearchStrategy(
        string $query = '',
        ?EquipmentType $equipmentType = null,
        string $status = 'all',
        ?array $restrictToClubs = null,
        ?array $allowedClubsAvailableOnly = [],
        array $allowedRegions = [],
        bool $onlyAvailableRegional = false,
        bool $includeAllAvailableRegional = false,
        bool $includeNational = false,
        bool $filterByBorrower = false,
        ?ClubMember $borrowerMember = null,
    ): QueryBuilder {
        $term = '' !== $query ? '%'.mb_strtolower($query).'%' : '';
        $searchStrategy = $this->getStrategyForType($equipmentType);

        $queryBuilder = $searchStrategy->buildQuery(
            $term,
            $status,
            $restrictToClubs,
            $allowedClubsAvailableOnly,
            $allowedRegions,
            $onlyAvailableRegional,
            $includeAllAvailableRegional,
            $includeNational,
        );

        if ($filterByBorrower) {
            $this->applyBorrowerFilter($queryBuilder, $borrowerMember);
        }

        return $queryBuilder;
    }

    /**
     * Filtre la query pour ne retourner que les équipements empruntés par un membre donné.
     * Si $borrowerMember est null (utilisateur introuvable ou sans ClubMember), force un résultat vide.
     */
    private function applyBorrowerFilter(QueryBuilder $queryBuilder, ?ClubMember $borrowerMember): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        if ($borrowerMember instanceof ClubMember) {
            $queryBuilder
                ->andWhere(sprintf('%s.borrowerMember = :borrowerMember', $alias))
                ->setParameter('borrowerMember', $borrowerMember);
        } else {
            $queryBuilder->andWhere('1 = 0');
        }
    }

    /**
     * Retourne la stratégie de recherche appropriée selon le type d'équipement.
     */
    private function getStrategyForType(?EquipmentType $equipmentType): SearchStrategyInterface
    {
        // On retourne la stratégie par défaut si le type d'équipement n'a aucune stratégie dédiée
        return $this->strategyMap[$equipmentType->value ?? ''] ?? $this->strategyMap[''];
    }
}
