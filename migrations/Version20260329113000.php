<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add length property to Yumi and size property to Glove equipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD strength INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD length VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment ADD size INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP strength');
        $this->addSql('ALTER TABLE equipment DROP length');
        $this->addSql('ALTER TABLE equipment DROP size');
    }
}
