<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420203900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS qr_code (id SERIAL NOT NULL, equipment_id INT DEFAULT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7D8B1FB5D17F50A6 ON qr_code (uuid)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7D8B1FB5517FE9FE ON qr_code (equipment_id)');
        $this->addSql('COMMENT ON COLUMN qr_code.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN qr_code.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_7d8b1fb5517fe9fe') THEN ALTER TABLE qr_code ADD CONSTRAINT FK_7D8B1FB5517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE qr_code DROP CONSTRAINT FK_7D8B1FB5517FE9FE');
        $this->addSql('DROP TABLE qr_code');
    }
}
