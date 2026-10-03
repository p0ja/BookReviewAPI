<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doctrine ignores expression indexes when comparing schemas (see
 * Version20260927125000), so doctrine:schema:validate stays in sync and
 * migrations:diff will not try to drop the index.
 */
final class Version20261003110000 extends AbstractMigration
{
    // Each author with the oldest author of the same name (trimmed, any letter case).
    private const KEEPERS = 'SELECT id, MIN(id) OVER (PARTITION BY LOWER(name)) AS keeper FROM author';

    public function getDescription(): string
    {
        return 'One author per name: trim names, merge authors that differ only in case or spacing, unique LOWER(name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE author SET name = BTRIM(name) WHERE name <> BTRIM(name)');

        // The oldest author of a name is kept; it takes the first info of its duplicates
        // when it has none.
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

        // A book linked to several authors of one name keeps a single link: the one to the
        // kept author, or else the oldest. The others would break UNIQ_BOOK_AUTHOR once
        // they all point at the kept author.
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
        // Merged authors stay merged; only the constraint goes.
        $this->addSql('DROP INDEX uniq_author_name_lower');
    }
}
