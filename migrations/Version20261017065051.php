<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017065051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Saved views of a Project (ticket 08)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saved_view (name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, filters JSONB NOT NULL, id VARCHAR(44) NOT NULL, project_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F0C2599B166D1F9C ON saved_view (project_id)');
        $this->addSql('CREATE INDEX IDX_F0C2599B82D40A1F ON saved_view (workspace_id)');
        $this->addSql('ALTER TABLE saved_view ADD CONSTRAINT FK_F0C2599B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE saved_view ADD CONSTRAINT FK_F0C2599B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_view DROP CONSTRAINT FK_F0C2599B166D1F9C');
        $this->addSql('ALTER TABLE saved_view DROP CONSTRAINT FK_F0C2599B82D40A1F');
        $this->addSql('DROP TABLE saved_view');
    }
}
