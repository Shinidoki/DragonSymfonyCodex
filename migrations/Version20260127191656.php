<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127191656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add settlement buildings and settlement projects (dojo v1).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('game_settlement_building')) {
            $this->addSql('CREATE TABLE game_settlement_building (id int AUTO_INCREMENT NOT NULL, settlement_id int NOT NULL, code varchar(32) NOT NULL, level int NOT NULL DEFAULT 0, created_at datetime NOT NULL, INDEX idx_settlement_building_settlement (settlement_id), UNIQUE INDEX uniq_settlement_building (settlement_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE game_settlement_building ADD CONSTRAINT FK_56AFA8346D5B1B5 FOREIGN KEY (settlement_id) REFERENCES game_settlement (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('game_settlement_project')) {
            $this->addSql('CREATE TABLE game_settlement_project (id int AUTO_INCREMENT NOT NULL, settlement_id int NOT NULL, building_code varchar(32) NOT NULL, target_level int NOT NULL, required_work_units int NOT NULL, progress_work_units int NOT NULL DEFAULT 0, status varchar(16) NOT NULL, started_day int NOT NULL, last_sim_day_applied int NOT NULL DEFAULT -1, request_event_id int DEFAULT NULL, created_at datetime NOT NULL, INDEX idx_settlement_project_settlement_status (settlement_id, status), UNIQUE INDEX uniq_settlement_project_request_event (request_event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE game_settlement_project ADD CONSTRAINT FK_7F7E70CB6D5B1B5 FOREIGN KEY (settlement_id) REFERENCES game_settlement (id) ON DELETE CASCADE');
        }

    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('game_settlement_project')) {
            $this->addSql('DROP TABLE game_settlement_project');
        }

        if ($schema->hasTable('game_settlement_building')) {
            $this->addSql('DROP TABLE game_settlement_building');
        }

    }
}
