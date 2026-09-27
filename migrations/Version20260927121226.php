<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927121226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Book data model: exact decimal price, real publish date, text description; an author once per book';
    }

    public function up(Schema $schema): void
    {
        // Refuse rather than silently change data that does not fit the new columns.
        $tooExpensive = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM book WHERE price > 99999999.99');
        $this->abortIf(
            $tooExpensive > 0,
            sprintf('%d book(s) cost more than 99999999.99, the most decimal(10,2) holds; fix their price first.', $tooExpensive),
        );
        $badDates = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM book WHERE TRIM(publish_date) <> '' AND TRIM(publish_date) !~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'",
        );
        $this->abortIf(
            $badDates > 0,
            sprintf('%d book(s) have a publish date that is not YYYY-MM-DD; fix or clear it first.', $badDates),
        );

        // An empty date becomes NULL; an impossible one (2023-02-30) fails the cast, and the
        // whole migration rolls back.
        $this->addSql("ALTER TABLE book ALTER publish_date TYPE DATE USING NULLIF(TRIM(publish_date), '')::date");
        $this->addSql("COMMENT ON COLUMN book.publish_date IS '(DC2Type:date_immutable)'");
        $this->addSql('ALTER TABLE book ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE book ALTER price TYPE NUMERIC(10, 2) USING ROUND(price::numeric, 2)');

        // Keep the oldest of any duplicate book-author links, then forbid new ones.
        $this->addSql(<<<'SQL'
            DELETE FROM book_author newer
            USING book_author older
            WHERE newer.book_id_id = older.book_id_id
              AND newer.author_id_id = older.author_id_id
              AND newer.id > older.id
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BOOK_AUTHOR ON book_author (book_id_id, author_id_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_BOOK_AUTHOR');
        $this->addSql('ALTER TABLE book ALTER price TYPE DOUBLE PRECISION USING price::double precision');
        // Descriptions longer than the old 255 characters are cut.
        $this->addSql('ALTER TABLE book ALTER description TYPE VARCHAR(255) USING LEFT(description, 255)');
        $this->addSql("ALTER TABLE book ALTER publish_date TYPE VARCHAR(25) USING TO_CHAR(publish_date, 'YYYY-MM-DD')");
        $this->addSql('COMMENT ON COLUMN book.publish_date IS NULL');
    }
}
