<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20261003110000 trimmed author names with BTRIM(), which removes spaces only,
 * while the application trims like PHP's trim(): tabs, line breaks and vertical tabs
 * too. A name stored as "Kent Beck\t" was then never found by the trimmed lookup, and
 * the next "Kent Beck" became a second author. This trims the rest and merges again,
 * the same way.
 */
final class Version20261003120000 extends AbstractMigration
{
    // What PHP's trim() removes, but NUL, which PostgreSQL text cannot hold.
    private const TRIMMED = '[ \t\n\r\v]+';

    // Each author with the oldest author of the same name (any letter case).
    private const KEEPERS = 'SELECT id, MIN(id) OVER (PARTITION BY LOWER(name)) AS keeper FROM author';

    public function getDescription(): string
    {
        return 'Trim author names like PHP trim() (tabs, line breaks), merging the authors that then share a name';
    }

    public function up(Schema $schema): void
    {
        // Trimming can give two authors the same name before they are merged.
        $this->addSql('DROP INDEX uniq_author_name_lower');

        $this->addSql(sprintf(
            "UPDATE author SET name = REGEXP_REPLACE(name, '^%1\$s|%1\$s\$', '', 'g') WHERE name ~ '^%1\$s|%1\$s\$'",
            self::TRIMMED,
        ));

        $this->addSql(<<<'SQL'
            UPDATE author kept
            SET info = (
                SELECT dup.info FROM author dup
                WHERE LOWER(dup.name) = LOWER(kept.name) AND dup.info IS NOT NULL
                ORDER BY dup.id LIMIT 1
            )
            WHERE kept.info IS NULL
              AND kept.id = (SELECT MIN(a.id) FROM author a WHERE LOWER(a.name) = LOWER(kept.name))
        SQL);

        $this->addSql(sprintf(<<<'SQL'
            DELETE FROM book_author link
            USING (%1$s) g
            WHERE link.author_id = g.id
              AND EXISTS (
                  SELECT 1 FROM book_author other
                  JOIN (%1$s) og ON og.id = other.author_id
                  WHERE other.book_id = link.book_id
                    AND og.keeper = g.keeper
                    AND other.id <> link.id
                    AND (other.author_id = g.keeper OR (link.author_id <> g.keeper AND other.id < link.id))
              )
        SQL, self::KEEPERS));

        $this->addSql(sprintf(
            'UPDATE book_author link SET author_id = g.keeper FROM (%s) g WHERE link.author_id = g.id AND g.id <> g.keeper',
            self::KEEPERS,
        ));
        $this->addSql(sprintf(
            'DELETE FROM author a USING (%s) g WHERE a.id = g.id AND g.id <> g.keeper',
            self::KEEPERS,
        ));

        $this->addSql('CREATE UNIQUE INDEX uniq_author_name_lower ON author (LOWER(name))');
    }

    public function down(Schema $schema): void
    {
        // Trimmed names stay trimmed, merged authors merged.
    }
}
