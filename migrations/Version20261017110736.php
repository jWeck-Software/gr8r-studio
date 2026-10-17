<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A Member's last activity becomes a moment (epoch milliseconds) instead of a fixed number of
 * minutes: `last` is counted from it whenever a Member's record is read. Existing minutes become
 * that many minutes before now; a Member who never signed in (null) stays null.
 */
final class Version20261017110736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store when a Member was last active (member.last_active_at) instead of minutes ago';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD last_active_at BIGINT DEFAULT NULL');
        $this->addSql('UPDATE member SET last_active_at = FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000) - last * 60000 WHERE last IS NOT NULL');
        $this->addSql('ALTER TABLE member DROP last');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD last INT DEFAULT NULL');
        $this->addSql('UPDATE member SET last = GREATEST(0, FLOOR((FLOOR(UNIX_TIMESTAMP(NOW(3)) * 1000) - last_active_at) / 60000)) WHERE last_active_at IS NOT NULL');
        $this->addSql('ALTER TABLE member DROP last_active_at');
    }
}
