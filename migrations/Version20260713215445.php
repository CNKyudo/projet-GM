<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713215445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Tsuru (tsuru_length, strength_min, strength_max)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD tsuru_length VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD strength_min DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD strength_max DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP tsuru_length');
        $this->addSql('ALTER TABLE equipment DROP strength_min');
        $this->addSql('ALTER TABLE equipment DROP strength_max');
    }
}
