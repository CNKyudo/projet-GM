<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table club_member (entité membre de club).
 * Remplace borrower_user_id par borrower_member_id sur equipment.
 */
final class Version20260419110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée club_member, remplace borrower_user_id par borrower_member_id sur equipment.';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Crée club_member si elle n'existe pas encore (fresh DB) ──────
        $this->addSql('CREATE TABLE IF NOT EXISTS club_member (
            id SERIAL NOT NULL,
            user_id INT DEFAULT NULL,
            club_id INT NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        // ── 2. Index sur club_member : renomme les anciens noms custom ou crée ─
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'uniq_club_member_user') THEN
                ALTER INDEX uniq_club_member_user RENAME TO UNIQ_552B46F2A76ED395;
            END IF;
        END \$\$");
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_552B46F2A76ED395 ON club_member (user_id)');

        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'idx_club_member_club') THEN
                ALTER INDEX idx_club_member_club RENAME TO IDX_552B46F261190A32;
            END IF;
        END \$\$");
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_552B46F261190A32 ON club_member (club_id)');

        // ── 3. FK sur club_member : supprime les anciens noms custom puis recrée ─
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_club_member_user') THEN
                ALTER TABLE club_member DROP CONSTRAINT fk_club_member_user;
            END IF;
        END \$\$");
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_club_member_club') THEN
                ALTER TABLE club_member DROP CONSTRAINT fk_club_member_club;
            END IF;
        END \$\$");
        $this->addSql("DO \$\$ BEGIN
            ALTER TABLE club_member ADD CONSTRAINT FK_552B46F2A76ED395
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
        EXCEPTION WHEN duplicate_object THEN NULL; END \$\$");
        $this->addSql("DO \$\$ BEGIN
            ALTER TABLE club_member ADD CONSTRAINT FK_552B46F261190A32
                FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
        EXCEPTION WHEN duplicate_object THEN NULL; END \$\$");

        // ── 4. Ajoute borrower_member_id sur equipment (fresh DB) ────────────
        $this->addSql("DO \$\$ BEGIN
            ALTER TABLE equipment ADD COLUMN borrower_member_id INT DEFAULT NULL;
        EXCEPTION WHEN duplicate_column THEN NULL; END \$\$");

        // ── 5. Index borrower_member : renomme l'ancien ou crée ──────────────
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'idx_equipment_borrower_member') THEN
                ALTER INDEX idx_equipment_borrower_member RENAME TO IDX_D338D583168F5EBE;
            END IF;
        END \$\$");
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D338D583168F5EBE ON equipment (borrower_member_id)');

        // ── 6. FK borrower_member : drop ancienne (ON DELETE SET NULL), recrée ─
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_equipment_borrower_member') THEN
                ALTER TABLE equipment DROP CONSTRAINT fk_equipment_borrower_member;
            END IF;
        END \$\$");
        $this->addSql("DO \$\$ BEGIN
            ALTER TABLE equipment ADD CONSTRAINT FK_D338D583168F5EBE
                FOREIGN KEY (borrower_member_id) REFERENCES club_member (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
        EXCEPTION WHEN duplicate_object THEN NULL; END \$\$");

        // ── 7. Supprime borrower_user_id (si existe encore) ──────────────────
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_D338D5834705A607') THEN
                ALTER TABLE equipment DROP CONSTRAINT FK_D338D5834705A607;
            END IF;
        END \$\$");
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'idx_d338d5834705a607') THEN
                DROP INDEX idx_d338d5834705a607;
            END IF;
        END \$\$");
        $this->addSql("DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'equipment' AND column_name = 'borrower_user_id'
            ) THEN
                ALTER TABLE equipment DROP COLUMN borrower_user_id;
            END IF;
        END \$\$");
    }

    public function down(Schema $schema): void
    {
        // Rétablit borrower_user_id
        $this->addSql('ALTER TABLE equipment ADD borrower_user_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_D338D5834705A607 ON equipment (borrower_user_id)');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D5834705A607 FOREIGN KEY (borrower_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Supprime borrower_member_id
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D583168F5EBE');
        $this->addSql('DROP INDEX IDX_D338D583168F5EBE');
        $this->addSql('ALTER TABLE equipment DROP borrower_member_id');

        // Supprime la table club_member
        $this->addSql('ALTER TABLE club_member DROP CONSTRAINT FK_552B46F2A76ED395');
        $this->addSql('ALTER TABLE club_member DROP CONSTRAINT FK_552B46F261190A32');
        $this->addSql('DROP TABLE club_member');
    }
}
