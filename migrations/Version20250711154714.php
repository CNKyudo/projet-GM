<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250711154714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, street_address VARCHAR(1023) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, country VARCHAR(1023) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club ADD address_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club ADD CONSTRAINT FK_B8EE3872F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_B8EE3872F5B7AF75 ON club (address_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE club DROP FOREIGN KEY FK_B8EE3872F5B7AF75
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE address
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_B8EE3872F5B7AF75 ON club
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club DROP address_id
        SQL);
    }
}
