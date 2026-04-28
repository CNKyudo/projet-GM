<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428175912 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD height DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD length DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD weight DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('UPDATE equipment SET height = hauteur');
        $this->addSql('UPDATE equipment SET length = longueur');
        $this->addSql('UPDATE equipment SET weight = poids');
        $this->addSql('ALTER TABLE equipment DROP hauteur');
        $this->addSql('ALTER TABLE equipment DROP longueur');
        $this->addSql('ALTER TABLE equipment DROP poids');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD hauteur DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD longueur DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD poids DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('UPDATE equipment SET hauteur = height');
        $this->addSql('UPDATE equipment SET longueur = length');
        $this->addSql('UPDATE equipment SET poids = weight');
        $this->addSql('ALTER TABLE equipment DROP height');
        $this->addSql('ALTER TABLE equipment DROP length');
        $this->addSql('ALTER TABLE equipment DROP weight');
    }
}
