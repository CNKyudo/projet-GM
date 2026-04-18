<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414115457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table club_members (ManyToMany User ↔ Club) et supprime la table qr_code orpheline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE IF EXISTS qr_code_id_seq CASCADE');
        $this->addSql('CREATE TABLE IF NOT EXISTS club_members (user_id INT NOT NULL, club_id INT NOT NULL, PRIMARY KEY(user_id, club_id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_48E8777DA76ED395 ON club_members (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_48E8777D61190A32 ON club_members (club_id)');
        $this->addSql('ALTER TABLE club_members DROP CONSTRAINT IF EXISTS FK_48E8777DA76ED395');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_members DROP CONSTRAINT IF EXISTS FK_48E8777D61190A32');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777D61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Suppression de la table qr_code orpheline si elle existe encore
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'qr_code') THEN ALTER TABLE qr_code DROP CONSTRAINT IF EXISTS fk_7d8b1fb5517fe9fe; DROP TABLE qr_code; END IF; END \$\$");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE qr_code_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE qr_code (id SERIAL NOT NULL, equipment_id INT DEFAULT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_7d8b1fb5517fe9fe ON qr_code (equipment_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_7d8b1fb5d17f50a6 ON qr_code (uuid)');
        $this->addSql('COMMENT ON COLUMN qr_code.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN qr_code.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE qr_code ADD CONSTRAINT fk_7d8b1fb5517fe9fe FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_members DROP CONSTRAINT FK_48E8777DA76ED395');
        $this->addSql('ALTER TABLE club_members DROP CONSTRAINT FK_48E8777D61190A32');
        $this->addSql('DROP TABLE club_members');
    }
}
