<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017001326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comments on Tasks with their Reactions (ticket 04)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE comment (author VARCHAR(44) NOT NULL, at BIGINT NOT NULL, text TEXT NOT NULL, re JSONB NOT NULL, id VARCHAR(44) NOT NULL, task_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9474526C8DB60186 ON comment (task_id)');
        $this->addSql('CREATE INDEX IDX_9474526C82D40A1F ON comment (workspace_id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT FK_9474526C8DB60186');
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT FK_9474526C82D40A1F');
        $this->addSql('DROP TABLE comment');
    }
}
