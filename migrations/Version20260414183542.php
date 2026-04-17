<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414183542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Federation, Region, user_managed_regions, Club.region_id + equipment_manager_id, Equipment.equipment_level + owner_region_id + owner_federation_id.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE federation (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE region (id SERIAL NOT NULL, federation_id INT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F62F1766A03EFC5 ON region (federation_id)');
        $this->addSql('CREATE TABLE user_managed_regions (user_id INT NOT NULL, region_id INT NOT NULL, PRIMARY KEY(user_id, region_id))');
        $this->addSql('CREATE INDEX IDX_D1AC1B91A76ED395 ON user_managed_regions (user_id)');
        $this->addSql('CREATE INDEX IDX_D1AC1B9198260155 ON user_managed_regions (region_id)');
        $this->addSql('ALTER TABLE region ADD CONSTRAINT FK_F62F1766A03EFC5 FOREIGN KEY (federation_id) REFERENCES federation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_managed_regions ADD CONSTRAINT FK_D1AC1B91A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_managed_regions ADD CONSTRAINT FK_D1AC1B9198260155 FOREIGN KEY (region_id) REFERENCES region (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club ADD equipment_manager_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD region_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE38725135C0BD FOREIGN KEY (equipment_manager_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE387298260155 FOREIGN KEY (region_id) REFERENCES region (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8EE38725135C0BD ON club (equipment_manager_id)');
        $this->addSql('CREATE INDEX IDX_B8EE387298260155 ON club (region_id)');
        $this->addSql('ALTER TABLE equipment ADD owner_region_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD owner_federation_id INT DEFAULT NULL');
        // Valeur par défaut 'club' pour tous les équipements existants
        $this->addSql("ALTER TABLE equipment ADD equipment_level VARCHAR(50) NOT NULL DEFAULT 'club'");
        $this->addSql('ALTER TABLE equipment ALTER COLUMN equipment_level DROP DEFAULT');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D5839A5D72E6 FOREIGN KEY (owner_region_id) REFERENCES region (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D58311B619A9 FOREIGN KEY (owner_federation_id) REFERENCES federation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D338D5839A5D72E6 ON equipment (owner_region_id)');
        $this->addSql('CREATE INDEX IDX_D338D58311B619A9 ON equipment (owner_federation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D58311B619A9');
        $this->addSql('ALTER TABLE club DROP CONSTRAINT FK_B8EE387298260155');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D5839A5D72E6');
        $this->addSql('ALTER TABLE region DROP CONSTRAINT FK_F62F1766A03EFC5');
        $this->addSql('ALTER TABLE user_managed_regions DROP CONSTRAINT FK_D1AC1B91A76ED395');
        $this->addSql('ALTER TABLE user_managed_regions DROP CONSTRAINT FK_D1AC1B9198260155');
        $this->addSql('DROP TABLE federation');
        $this->addSql('DROP TABLE region');
        $this->addSql('DROP TABLE user_managed_regions');
        $this->addSql('DROP INDEX IDX_D338D5839A5D72E6');
        $this->addSql('DROP INDEX IDX_D338D58311B619A9');
        $this->addSql('ALTER TABLE equipment DROP owner_region_id');
        $this->addSql('ALTER TABLE equipment DROP owner_federation_id');
        $this->addSql('ALTER TABLE equipment DROP equipment_level');
        $this->addSql('ALTER TABLE club DROP CONSTRAINT FK_B8EE38725135C0BD');
        $this->addSql('DROP INDEX UNIQ_B8EE38725135C0BD');
        $this->addSql('DROP INDEX IDX_B8EE387298260155');
        $this->addSql('ALTER TABLE club DROP equipment_manager_id');
        $this->addSql('ALTER TABLE club DROP region_id');
    }
}
