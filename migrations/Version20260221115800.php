<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221115800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add simulation_daily_kpi table for per-day simulation metrics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE simulation_daily_kpi (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, world_id INTEGER NOT NULL, day INTEGER NOT NULL, settlements_active INTEGER NOT NULL, population_total INTEGER NOT NULL, unemployed_count INTEGER NOT NULL, unemployment_rate DOUBLE PRECISION NOT NULL, migration_commits INTEGER NOT NULL, tournament_announced INTEGER NOT NULL, tournament_resolved INTEGER NOT NULL, tournament_canceled INTEGER NOT NULL, mean_settlement_prosperity DOUBLE PRECISION NOT NULL, mean_settlement_treasury DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_72F2A2A7D5F57D8 FOREIGN KEY (world_id) REFERENCES world (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_sim_daily_kpi_world_day ON simulation_daily_kpi (world_id, day)');
        $this->addSql('CREATE INDEX idx_sim_daily_kpi_world_day ON simulation_daily_kpi (world_id, day)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE simulation_daily_kpi');
    }
}
