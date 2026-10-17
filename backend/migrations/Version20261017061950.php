<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attachments are no longer stored with their Task: they are the Files attached to it.
 */
final class Version20261017061950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A Task\'s Attachments are its Files (ticket 07)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP attachments');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD attachments JSONB DEFAULT \'[]\' NOT NULL');
    }
}
