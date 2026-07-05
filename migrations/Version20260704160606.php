<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260704160606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity field to Etafoam (default 1, NOT NULL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD quantity INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE equipment ALTER state DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP quantity');
        $this->addSql('ALTER TABLE equipment ALTER state SET DEFAULT \'new\'');
    }
}
