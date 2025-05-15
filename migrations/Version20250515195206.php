<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515195206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE book ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER publish_date TYPE VARCHAR(25)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER publish_date DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN book.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN book.publish_date IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book DROP created_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER publish_date TYPE DATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER publish_date SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER publish_date TYPE DATE
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN book.publish_date IS '(DC2Type:date_immutable)'
        SQL);
    }
}
