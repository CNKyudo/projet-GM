<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Equipment;
use App\Entity\Federation;
use App\Entity\Region;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Loggable\Entity\LogEntry;

class LogEntryEnricher
{
    private const array FIELD_MAPS = [
        Equipment::class => [
            'ownerClub' => Club::class,
            'ownerRegion' => Region::class,
            'ownerFederation' => Federation::class,
            'borrowerClub' => Club::class,
            'borrowerMember' => ClubMember::class,
        ],
    ];

    private const array LABEL_MAPS = [
        Club::class => 'name',
        Region::class => 'name',
        Federation::class => 'name',
        ClubMember::class => 'fullName',
    ];

    public function __construct(
        private readonly LogEntryRepository $logEntryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Récupère les entrées de log pour une entité donnée et enrichit les champs
     * qui représentent des relations ManyToOne (stockés sous forme d'ID) en allant
     * chercher les entités liées en base, afin d'afficher un libellé pertinent
     * (nom, fullName, etc.) au lieu du seul identifiant numérique.
     *
     * @return LogEntry<object>[]
     */
    public function getRichLogEntries(object $entity): array
    {
        $logEntries = $this->logEntryRepository->findByEntity($entity);

        $fieldEntityMap = [];

        foreach (self::FIELD_MAPS as $class => $map) {
            if ($entity instanceof $class) {
                $fieldEntityMap = $map;
                break;
            }
        }

        if ([] === $fieldEntityMap) {
            return $logEntries;
        }

        $idsByClass = [];
        $fieldTargets = [];

        // On construit une liste de tous les objets à récupérer en base
        foreach ($logEntries as $entry) {
            $data = $entry->getData();

            if (null === $data) {
                continue;
            }

            foreach ($data as $field => $value) {
                if (!\is_array($value) || !isset($value['id'])) {
                    continue;
                }

                $targetClass = $fieldEntityMap[$field] ?? null;

                if (null === $targetClass) {
                    continue;
                }

                $id = (int) $value['id'];
                $idsByClass[$targetClass][$id] = $id;
                $fieldTargets[] = [$entry, $field, $targetClass, $id];
            }
        }

        $entityCache = [];

        // On récupère les objets en base en un nombre minimum de queries
        foreach ($idsByClass as $class => $ids) {
            $entities = $this->entityManager->getRepository($class)->findBy(['id' => $ids]);

            foreach ($entities as $cachedEntity) {
                $entityCache[$class][(int) $cachedEntity->getId()] = $cachedEntity;
            }
        }

        // On enrichit les log entries pour afficher les valeurs pertinentes des relations au lieu de leur id seul
        foreach ($fieldTargets as [$entry, $field, $class, $id]) {
            $cachedEntity = $entityCache[$class][$id] ?? null;

            if (null === $cachedEntity) {
                continue;
            }

            $data = $entry->getData();
            $data[$field] = [
                'id' => $id,
                'display' => $this->resolveLabel($cachedEntity, $class),
            ];
            $entry->setData($data);
        }

        return $logEntries;
    }

    private function resolveLabel(object $entity, string $class): string
    {
        $property = self::LABEL_MAPS[$class] ?? 'id';
        $getter = match ($property) {
            'id' => 'getId',
            'fullName' => 'getFullName',
            default => 'get'.ucfirst($property),
        };

        if (!method_exists($entity, $getter)) {
            return method_exists($entity, 'getId') ? (string) $entity->getId() : '';
        }

        return (string) $entity->$getter();
    }
}
