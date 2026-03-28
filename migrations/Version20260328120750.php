<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to make postal_code and city fields NOT NULL in address table
 */
final class Version20260328120750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make postal_code and city fields NOT NULL in address table';
    }

    public function up(Schema $schema): void
    {
        // First, set default values for any NULL rows
        $this->addSql('UPDATE address SET postal_code = \'\' WHERE postal_code IS NULL');
        $this->addSql('UPDATE address SET city = \'\' WHERE city IS NULL');

        // Then alter the columns to NOT NULL
        $this->addSql('ALTER TABLE address ALTER postal_code SET NOT NULL');
        $this->addSql('ALTER TABLE address ALTER city SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Rollback: revert columns to nullable
        $this->addSql('ALTER TABLE address ALTER postal_code DROP NOT NULL');
        $this->addSql('ALTER TABLE address ALTER city DROP NOT NULL');
    }
}
