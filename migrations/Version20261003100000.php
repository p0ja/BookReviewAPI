<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Book updated_at, starting at created_at for existing books';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN book.updated_at IS '(DC2Type:datetime_immutable)'");
        // The last known write of an existing book is its creation.
        $this->addSql('UPDATE book SET updated_at = created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book DROP updated_at');
    }
}
