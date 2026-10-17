<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017011740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deleted Tasks, kept so undo does not notify their assignees again (ticket 05c)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deleted_task (id VARCHAR(44) NOT NULL, record JSONB NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7A5663082D40A1F ON deleted_task (workspace_id)');
        $this->addSql('ALTER TABLE deleted_task ADD CONSTRAINT FK_7A5663082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deleted_task DROP CONSTRAINT FK_7A5663082D40A1F');
        $this->addSql('DROP TABLE deleted_task');
    }
}
