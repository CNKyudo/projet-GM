<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250711160215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE equipment (id INT AUTO_INCREMENT NOT NULL, owner_club_id INT DEFAULT NULL, borrower_club_id INT DEFAULT NULL, borrower_user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, equipment_type VARCHAR(255) NOT NULL, material VARCHAR(255) DEFAULT NULL, nb_fingers INT DEFAULT NULL, INDEX IDX_D338D583ED6F8CED (owner_club_id), INDEX IDX_D338D58381727FA0 (borrower_club_id), INDEX IDX_D338D5834705A607 (borrower_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment ADD CONSTRAINT FK_D338D583ED6F8CED FOREIGN KEY (owner_club_id) REFERENCES club (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment ADD CONSTRAINT FK_D338D58381727FA0 FOREIGN KEY (borrower_club_id) REFERENCES club (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment ADD CONSTRAINT FK_D338D5834705A607 FOREIGN KEY (borrower_user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment DROP FOREIGN KEY FK_D338D583ED6F8CED
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment DROP FOREIGN KEY FK_D338D58381727FA0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipment DROP FOREIGN KEY FK_D338D5834705A607
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE equipment
        SQL);
    }
}
