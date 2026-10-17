<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The whole schema on MariaDB 10.11, generated from the mappings (it replaces the PostgreSQL
 * migrations, whose data migrations had nothing to do on a fresh database).
 */
final class Version20261017094512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The schema on MariaDB';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity (id VARCHAR(44) NOT NULL, author VARCHAR(44) NOT NULL, verb LONGTEXT NOT NULL, task VARCHAR(44) DEFAULT NULL, project VARCHAR(44) DEFAULT NULL, at BIGINT NOT NULL, extra LONGTEXT NOT NULL, workspace_id INT NOT NULL, INDEX activity_project (project), INDEX IDX_AC74095A82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL COLLATE `utf8mb4_bin`, name VARCHAR(120) NOT NULL, verified TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment (author VARCHAR(44) NOT NULL, at BIGINT NOT NULL, text LONGTEXT NOT NULL, re JSON NOT NULL, id VARCHAR(44) NOT NULL, task_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, INDEX IDX_9474526C8DB60186 (task_id), INDEX IDX_9474526C82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE deleted_comment (id VARCHAR(44) NOT NULL, task VARCHAR(44) NOT NULL, record JSON NOT NULL, workspace_id INT NOT NULL, INDEX IDX_CEF3E80B82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE deleted_task (id VARCHAR(44) NOT NULL, record JSON NOT NULL, workspace_id INT NOT NULL, INDEX IDX_7A5663082D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE event (title VARCHAR(200) NOT NULL, date VARCHAR(10) NOT NULL, time VARCHAR(5) NOT NULL, id VARCHAR(44) NOT NULL, project_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, INDEX IDX_3BAE0AA7166D1F9C (project_id), INDEX IDX_3BAE0AA782D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE file (type VARCHAR(20) NOT NULL, id VARCHAR(44) NOT NULL, name VARCHAR(255) NOT NULL, size VARCHAR(20) NOT NULL, uploader VARCHAR(44) NOT NULL, at BIGINT NOT NULL, workspace_id INT NOT NULL, project_id VARCHAR(44) NOT NULL, task_id VARCHAR(44) DEFAULT NULL, INDEX IDX_8C9F361082D40A1F (workspace_id), INDEX IDX_8C9F3610166D1F9C (project_id), INDEX IDX_8C9F36108DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE member (favorites JSON NOT NULL, personal JSON DEFAULT \'{}\' NOT NULL, id VARCHAR(44) NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, team VARCHAR(44) NOT NULL, title VARCHAR(120) NOT NULL, c VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, last INT DEFAULT NULL, tz VARCHAR(80) NOT NULL, workspace_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_70E4FA7882D40A1F (workspace_id), INDEX IDX_70E4FA78A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (is_read TINYINT NOT NULL, id VARCHAR(44) NOT NULL, type VARCHAR(20) NOT NULL, author VARCHAR(44) DEFAULT NULL, task VARCHAR(44) DEFAULT NULL, project VARCHAR(44) DEFAULT NULL, text LONGTEXT NOT NULL, snippet LONGTEXT NOT NULL, at BIGINT NOT NULL, workspace_id INT NOT NULL, recipient_id VARCHAR(44) NOT NULL, INDEX IDX_BF5476CA82D40A1F (workspace_id), INDEX IDX_BF5476CAE92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE one_time_token (purpose VARCHAR(20) NOT NULL, subject VARCHAR(64) NOT NULL COLLATE `utf8mb4_bin`, hash VARCHAR(64) NOT NULL COLLATE `utf8mb4_bin`, expires_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5D9F8E3DD1B862B8 (hash), PRIMARY KEY (purpose, subject)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE project (project_key VARCHAR(20) NOT NULL, name VARCHAR(200) NOT NULL, icon VARCHAR(60) NOT NULL, color VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, team VARCHAR(44) NOT NULL, lead VARCHAR(44) DEFAULT NULL, due DATE DEFAULT NULL, start DATE DEFAULT NULL, members JSON NOT NULL, description LONGTEXT NOT NULL, milestones JSON NOT NULL, last INT DEFAULT NULL, is_private TINYINT DEFAULT NULL, archived_at BIGINT DEFAULT NULL, archived TINYINT DEFAULT NULL, id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, INDEX IDX_2FB3D0EE82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE saved_view (name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, filters JSON NOT NULL, id VARCHAR(44) NOT NULL, project_id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, INDEX IDX_F0C2599B166D1F9C (project_id), INDEX IDX_F0C2599B82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task (task_key VARCHAR(30) NOT NULL, title LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, due DATE DEFAULT NULL, start DATE DEFAULT NULL, labels JSON NOT NULL, subtasks JSON NOT NULL, deps JSON NOT NULL, description LONGTEXT NOT NULL, estimate VARCHAR(40) DEFAULT NULL, created BIGINT NOT NULL, updated BIGINT NOT NULL, sort_order DOUBLE PRECISION NOT NULL, recur VARCHAR(40) DEFAULT NULL, completed_at BIGINT DEFAULT NULL, prev_status VARCHAR(20) DEFAULT NULL, archived_at BIGINT DEFAULT NULL, archived TINYINT DEFAULT NULL, id VARCHAR(44) NOT NULL, project_id VARCHAR(44) NOT NULL, assignee_id VARCHAR(44) DEFAULT NULL, workspace_id INT NOT NULL, UNIQUE INDEX task_key_per_project (project_id, task_key), INDEX IDX_527EDB25166D1F9C (project_id), INDEX IDX_527EDB2559EC7D60 (assignee_id), INDEX IDX_527EDB2582D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team (name VARCHAR(120) NOT NULL, icon VARCHAR(60) NOT NULL, c VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, id VARCHAR(44) NOT NULL, workspace_id INT NOT NULL, INDEX IDX_C4E0A61F82D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE workspace (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, url VARCHAR(120) NOT NULL, c VARCHAR(20) NOT NULL, brand TINYINT NOT NULL, proj_order JSON NOT NULL, UNIQUE INDEX UNIQ_8D940019F47645AE (url), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deleted_comment ADD CONSTRAINT FK_CEF3E80B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deleted_task ADD CONSTRAINT FK_7A5663082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA782D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361082D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F36108DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA7882D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE saved_view ADD CONSTRAINT FK_F0C2599B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE saved_view ADD CONSTRAINT FK_F0C2599B82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2559EC7D60 FOREIGN KEY (assignee_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2582D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A82D40A1F');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C8DB60186');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C82D40A1F');
        $this->addSql('ALTER TABLE deleted_comment DROP FOREIGN KEY FK_CEF3E80B82D40A1F');
        $this->addSql('ALTER TABLE deleted_task DROP FOREIGN KEY FK_7A5663082D40A1F');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7166D1F9C');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA782D40A1F');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F361082D40A1F');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610166D1F9C');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F36108DB60186');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA7882D40A1F');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA78A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA82D40A1F');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE82D40A1F');
        $this->addSql('ALTER TABLE saved_view DROP FOREIGN KEY FK_F0C2599B166D1F9C');
        $this->addSql('ALTER TABLE saved_view DROP FOREIGN KEY FK_F0C2599B82D40A1F');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25166D1F9C');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2559EC7D60');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2582D40A1F');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F82D40A1F');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE deleted_comment');
        $this->addSql('DROP TABLE deleted_task');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE one_time_token');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE saved_view');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE workspace');
    }
}
