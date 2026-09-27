<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An index for the case-insensitive email lookups of UserRepository (registration and
 * login in another letter case).
 *
 * The entity mapping cannot express an index on an expression, so it lives only here.
 * Doctrine ignores expression indexes when comparing schemas: doctrine:schema:validate
 * stays in sync and migrations:diff will not try to drop it.
 */
final class Version20260927125000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index on LOWER(users.email) for case-insensitive email lookups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_users_email_lower ON "users" (LOWER(email))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_email_lower');
    }
}
