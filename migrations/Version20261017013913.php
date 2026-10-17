<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017013913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Teams of a Workspace (ticket 06)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE team (name VARCHAR(120) NOT NULL, icon VARCHAR(60) NOT NULL, c VARCHAR(20) NOT NULL, description TEXT NOT NULL, id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C4E0A61F82D40A1F ON team (workspace_id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F82D40A1F');
        $this->addSql('DROP TABLE team');
    }
}
