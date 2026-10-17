<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016235812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favorites per Member instead of one flag per Project and Task (ticket 03)';
    }

    public function up(Schema $schema): void
    {
        // Existing stars were shared by everyone, so nobody owns them: they are dropped.
        $this->addSql("ALTER TABLE member ADD favorites JSONB DEFAULT '{}' NOT NULL");
        $this->addSql('ALTER TABLE member ALTER favorites DROP DEFAULT');
        $this->addSql('ALTER TABLE project DROP fav');
        $this->addSql('ALTER TABLE task DROP fav');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP favorites');
        $this->addSql('ALTER TABLE project ADD fav BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE project ALTER fav DROP DEFAULT');
        $this->addSql('ALTER TABLE task ADD fav BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE task ALTER fav DROP DEFAULT');
    }
}
