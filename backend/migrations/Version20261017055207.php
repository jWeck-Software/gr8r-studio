<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261017055207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invites of invited Members (ticket 06)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invite (token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, member_id VARCHAR(44) NOT NULL, PRIMARY KEY (member_id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7E210D7B3BC57DA ON invite (token_hash)');
        $this->addSql('ALTER TABLE invite ADD CONSTRAINT FK_C7E210D77597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invite DROP CONSTRAINT FK_C7E210D77597D3FE');
        $this->addSql('DROP TABLE invite');
    }
}
