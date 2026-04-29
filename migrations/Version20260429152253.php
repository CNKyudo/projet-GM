<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429152253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_75ea56e016ba31db');
        $this->addSql('DROP INDEX idx_75ea56e0e3bd61ce');
        $this->addSql('DROP INDEX idx_75ea56e0fb7336f0');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE equipment ADD height DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD nb_bows INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD orientation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD nb_arrows INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD equipment_length DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD weight DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD attachment VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD width DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD thickness DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        $this->addSql('ALTER TABLE equipment DROP height');
        $this->addSql('ALTER TABLE equipment DROP nb_bows');
        $this->addSql('ALTER TABLE equipment DROP orientation');
        $this->addSql('ALTER TABLE equipment DROP nb_arrows');
        $this->addSql('ALTER TABLE equipment DROP equipment_length');
        $this->addSql('ALTER TABLE equipment DROP weight');
        $this->addSql('ALTER TABLE equipment DROP attachment');
        $this->addSql('ALTER TABLE equipment DROP width');
        $this->addSql('ALTER TABLE equipment DROP thickness');
    }
}
