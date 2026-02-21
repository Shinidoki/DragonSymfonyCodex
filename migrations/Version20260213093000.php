<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hard cut local-zone schema: drop local_* tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS local_intent');
        $this->addSql('DROP TABLE IF EXISTS local_event');
        $this->addSql('DROP TABLE IF EXISTS local_combatant');
        $this->addSql('DROP TABLE IF EXISTS local_combat');
        $this->addSql('DROP TABLE IF EXISTS local_actor');
        $this->addSql('DROP TABLE IF EXISTS local_session');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Local-zone schema hard-cut is irreversible.');
    }
}
