<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016235724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'When Tasks and Projects were archived (ticket 03)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD archived_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD archived_at BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP archived_at');
        $this->addSql('ALTER TABLE task DROP archived_at');
    }
}
