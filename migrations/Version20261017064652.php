<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017064652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A Member\'s own Preferences, view settings, notification settings and recent searches (ticket 08)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD personal JSONB DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP personal');
    }
}
