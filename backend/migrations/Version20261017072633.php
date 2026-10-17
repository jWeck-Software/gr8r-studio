<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017072633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'One-time links of every purpose in one table, the open Invites moved into it (ticket 09)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE one_time_token (purpose VARCHAR(20) NOT NULL, subject VARCHAR(64) NOT NULL, hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (purpose, subject))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D9F8E3DD1B862B8 ON one_time_token (hash)');
        $this->addSql("INSERT INTO one_time_token (purpose, subject, hash, expires_at) SELECT 'invite', member_id, token_hash, expires_at FROM invite");
        $this->addSql('ALTER TABLE invite DROP CONSTRAINT fk_c7e210d77597d3fe');
        $this->addSql('DROP TABLE invite');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invite (token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, member_id VARCHAR(44) NOT NULL, PRIMARY KEY (member_id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_c7e210d7b3bc57da ON invite (token_hash)');
        $this->addSql('ALTER TABLE invite ADD CONSTRAINT fk_c7e210d77597d3fe FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("INSERT INTO invite (member_id, token_hash, expires_at) SELECT t.subject, t.hash, t.expires_at FROM one_time_token t JOIN member m ON m.id = t.subject WHERE t.purpose = 'invite'");
        $this->addSql('DROP TABLE one_time_token');
    }
}
