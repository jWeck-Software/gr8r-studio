<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017003214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications, one recipient each (ticket 05)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (is_read BOOLEAN NOT NULL, id VARCHAR(44) NOT NULL, type VARCHAR(20) NOT NULL, author VARCHAR(44) DEFAULT NULL, task VARCHAR(44) DEFAULT NULL, project VARCHAR(44) DEFAULT NULL, text TEXT NOT NULL, snippet TEXT NOT NULL, at BIGINT NOT NULL, workspace_id INT NOT NULL, recipient_id VARCHAR(44) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BF5476CA82D40A1F ON notification (workspace_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CAE92F8F78 ON notification (recipient_id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA82D40A1F');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAE92F8F78');
        $this->addSql('DROP TABLE notification');
    }
}
