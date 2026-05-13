<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Loggable\Entity\LogEntry;

/**
 * @extends ServiceEntityRepository<LogEntry>
 */
class LogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogEntry::class);
    }

    /**
     * @return LogEntry<object>[]
     */
    public function findByEntity(object $entity): array
    {
        if (!method_exists($entity, 'getId')) {
            return [];
        }

        return $this->findBy(
            ['objectId' => (string) $entity->getId(), 'objectClass' => $entity::class],
            ['loggedAt' => 'DESC', 'version' => 'DESC'],
        );
    }
}
