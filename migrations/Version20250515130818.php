<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515130818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE author (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, info VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE book (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, isbn VARCHAR(255) NOT NULL, publish_date DATE NOT NULL, description VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION NOT NULL, genre VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_CBE5A331CC1CF4E6 ON book (isbn)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN book.publish_date IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE book_author (id SERIAL NOT NULL, book_id_id INT DEFAULT NULL, author_id_id INT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9478D34571868B2E ON book_author (book_id_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9478D34569CCBE9A ON book_author (author_id_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book_author ADD CONSTRAINT FK_9478D34571868B2E FOREIGN KEY (book_id_id) REFERENCES book (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book_author ADD CONSTRAINT FK_9478D34569CCBE9A FOREIGN KEY (author_id_id) REFERENCES author (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book_author DROP CONSTRAINT FK_9478D34571868B2E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book_author DROP CONSTRAINT FK_9478D34569CCBE9A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE author
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE book
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE book_author
        SQL);
    }
}
