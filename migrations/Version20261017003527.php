<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017003527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deleted Comments, kept so undo restores them as their authors\' (ticket 05)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deleted_comment (id VARCHAR(44) NOT NULL, task VARCHAR(44) NOT NULL, record JSONB NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CEF3E80B82D40A1F ON deleted_comment (workspace_id)');
        $this->addSql('ALTER TABLE deleted_comment ADD CONSTRAINT FK_CEF3E80B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deleted_comment DROP CONSTRAINT FK_CEF3E80B82D40A1F');
        $this->addSql('DROP TABLE deleted_comment');
    }
}
