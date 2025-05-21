<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519062754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE review (id SERIAL NOT NULL, book_id_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, content TEXT NOT NULL, rating INT NOT NULL, submit_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_794381C671868B2E ON review (book_id_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN review.submit_date IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C671868B2E FOREIGN KEY (book_id_id) REFERENCES book (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER roles SET DEFAULT '[]'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER password SET DEFAULT ''
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP CONSTRAINT FK_794381C671868B2E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "users" ALTER roles DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "users" ALTER password DROP DEFAULT
        SQL);
    }
}
