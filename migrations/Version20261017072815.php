<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017072815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Whether a User verified their email address; everyone so far did, by an Invite or the demo seed (ticket 09)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD verified BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP verified');
    }
}
