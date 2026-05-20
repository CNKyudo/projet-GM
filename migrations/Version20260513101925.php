<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace email-based usernames in ext_log_entries with the corresponding user ID.
 *
 * After this migration, all existing log entries will use the user ID (matching
 * the new UserIdActorProvider behavior) instead of the user's email address.
 */
final class Version20260513101925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace email-based usernames in ext_log_entries with user IDs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            UPDATE ext_log_entries
            SET username = u.id::text
            FROM users u
            WHERE u.email = ext_log_entries.username
              AND ext_log_entries.username IS NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Irreversible: the original email values cannot be recovered from IDs.
        $this->write('The original email values cannot be recovered from stored user IDs. '
            .'This migration is not reversible.');
    }
}
