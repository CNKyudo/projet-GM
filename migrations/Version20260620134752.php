<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620134752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename equipment type value from "glove" to "gake"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE equipment SET equipment_type = 'gake' WHERE equipment_type = 'glove'");
        $this->addSql('ALTER TABLE equipment ADD diameter DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE equipment SET equipment_type = 'glove' WHERE equipment_type = 'gake'");
        $this->addSql('ALTER TABLE equipment DROP diameter');
    }
}
