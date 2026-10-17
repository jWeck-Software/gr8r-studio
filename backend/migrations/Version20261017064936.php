<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017064936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events on a Project\'s calendar (ticket 08)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event (title VARCHAR(200) NOT NULL, date VARCHAR(10) NOT NULL, time VARCHAR(5) NOT NULL, id VARCHAR(44) NOT NULL, project_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7166D1F9C ON event (project_id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA782D40A1F ON event (workspace_id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA782D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7166D1F9C');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA782D40A1F');
        $this->addSql('DROP TABLE event');
    }
}
