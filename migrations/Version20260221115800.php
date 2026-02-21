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
        $this->addSql('CREATE TABLE simulation_daily_kpi (id int AUTO_INCREMENT NOT NULL, world_id int NOT NULL, day int NOT NULL, settlements_active int NOT NULL, population_total int NOT NULL, unemployed_count int NOT NULL, unemployment_rate DOUBLE PRECISION NOT NULL, migration_commits int NOT NULL, tournament_announced int NOT NULL, tournament_resolved int NOT NULL, tournament_canceled int NOT NULL, mean_settlement_prosperity DOUBLE PRECISION NOT NULL, mean_settlement_treasury DOUBLE PRECISION NOT NULL, created_at datetime NOT NULL COMMENT ''(DC2Type:datetime_immutable)'', INDEX idx_sim_daily_kpi_world_day (world_id, day), UNIQUE INDEX uniq_sim_daily_kpi_world_day (world_id, day), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE simulation_daily_kpi ADD CONSTRAINT FK_72F2A2A7D5F57D8 FOREIGN KEY (world_id) REFERENCES world (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE simulation_daily_kpi');
    }
}
