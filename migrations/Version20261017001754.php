<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017001754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The Activity feed, append-only (ticket 04)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity (id VARCHAR(44) NOT NULL, author VARCHAR(44) NOT NULL, verb TEXT NOT NULL, task VARCHAR(44) DEFAULT NULL, project VARCHAR(44) DEFAULT NULL, at BIGINT NOT NULL, extra TEXT NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX activity_project ON activity (project)');
        $this->addSql('CREATE INDEX IDX_AC74095A82D40A1F ON activity (workspace_id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A82D40A1F');
        $this->addSql('DROP TABLE activity');
    }
}
