<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Files of Projects and Tasks; their blobs live outside the database.
 */
final class Version20261017061550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Files of Projects and Tasks (ticket 07)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE file (type VARCHAR(20) NOT NULL, id VARCHAR(44) NOT NULL, name VARCHAR(255) NOT NULL, size VARCHAR(20) NOT NULL, uploader VARCHAR(44) NOT NULL, at BIGINT NOT NULL, workspace_id INT NOT NULL, project_id VARCHAR(44) NOT NULL, task_id VARCHAR(44) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8C9F361082D40A1F ON file (workspace_id)');
        $this->addSql('CREATE INDEX IDX_8C9F3610166D1F9C ON file (project_id)');
        $this->addSql('CREATE INDEX IDX_8C9F36108DB60186 ON file (task_id)');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F36108DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F361082D40A1F');
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F3610166D1F9C');
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_8C9F36108DB60186');
        $this->addSql('DROP TABLE file');
    }
}
