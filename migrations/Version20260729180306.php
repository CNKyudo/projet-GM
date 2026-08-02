<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729180306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename equipment type etafoam to azuchi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE equipment SET equipment_type = 'azuchi' WHERE equipment_type = 'etafoam'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE equipment SET equipment_type = 'etafoam' WHERE equipment_type = 'azuchi'");
    }
}
