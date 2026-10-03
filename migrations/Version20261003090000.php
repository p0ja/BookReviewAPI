<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename book_id_id/author_id_id to book_id/author_id, keeping the data';
    }

    public function up(Schema $schema): void
    {
        // Renamed in place: the generated diff drops and re-adds the book_author columns,
        // which would lose every book-author link. Constraint and index names are the
        // ones Doctrine derives from the new column names, so the schema validates.
        $this->addSql('ALTER TABLE review RENAME COLUMN book_id_id TO book_id');
        $this->addSql('ALTER TABLE review RENAME CONSTRAINT fk_794381c671868b2e TO FK_794381C616A2B381');
        $this->addSql('ALTER INDEX idx_794381c671868b2e RENAME TO IDX_794381C616A2B381');

        $this->addSql('ALTER TABLE book_author RENAME COLUMN book_id_id TO book_id');
        $this->addSql('ALTER TABLE book_author RENAME COLUMN author_id_id TO author_id');
        $this->addSql('ALTER TABLE book_author RENAME CONSTRAINT fk_9478d34571868b2e TO FK_9478D34516A2B381');
        $this->addSql('ALTER TABLE book_author RENAME CONSTRAINT fk_9478d34569ccbe9a TO FK_9478D345F675F31B');
        $this->addSql('ALTER INDEX idx_9478d34571868b2e RENAME TO IDX_9478D34516A2B381');
        $this->addSql('ALTER INDEX idx_9478d34569ccbe9a RENAME TO IDX_9478D345F675F31B');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_9478D345F675F31B RENAME TO idx_9478d34569ccbe9a');
        $this->addSql('ALTER INDEX IDX_9478D34516A2B381 RENAME TO idx_9478d34571868b2e');
        $this->addSql('ALTER TABLE book_author RENAME CONSTRAINT FK_9478D345F675F31B TO fk_9478d34569ccbe9a');
        $this->addSql('ALTER TABLE book_author RENAME CONSTRAINT FK_9478D34516A2B381 TO fk_9478d34571868b2e');
        $this->addSql('ALTER TABLE book_author RENAME COLUMN author_id TO author_id_id');
        $this->addSql('ALTER TABLE book_author RENAME COLUMN book_id TO book_id_id');

        $this->addSql('ALTER INDEX IDX_794381C616A2B381 RENAME TO idx_794381c671868b2e');
        $this->addSql('ALTER TABLE review RENAME CONSTRAINT FK_794381C616A2B381 TO fk_794381c671868b2e');
        $this->addSql('ALTER TABLE review RENAME COLUMN book_id TO book_id_id');
    }
}
