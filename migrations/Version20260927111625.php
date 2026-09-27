<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927111625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reviews belong to the user who posted them (null for older reviews)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD user_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C6A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_794381C6A76ED395 ON review (user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP CONSTRAINT FK_794381C6A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_794381C6A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP user_id
        SQL);
    }
}
