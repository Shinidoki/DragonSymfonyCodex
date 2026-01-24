<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add character goals and character/world events for simulation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_character_goal (id int AUTO_INCREMENT NOT NULL, character_id int NOT NULL, life_goal_code varchar(128) DEFAULT NULL, current_goal_code varchar(128) DEFAULT NULL, current_goal_data json DEFAULT NULL, current_goal_complete tinyint DEFAULT 0 NOT NULL, last_resolved_day int DEFAULT -1 NOT NULL, last_processed_event_id int DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_character_goal_character (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_character_goal ADD CONSTRAINT FK_2C61A22D1136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE game_character_event (id int AUTO_INCREMENT NOT NULL, world_id int NOT NULL, character_id int DEFAULT NULL, type varchar(128) NOT NULL, day int NOT NULL, created_at datetime_immutable NOT NULL, data json DEFAULT NULL, INDEX idx_character_event_world_day (world_id, DAY), INDEX idx_character_event_world_type_day (world_id, TYPE, DAY), INDEX idx_character_event_character_day (character_id, DAY), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_character_event ADD CONSTRAINT FK_4E20C07A8925311C FOREIGN KEY (world_id) REFERENCES world (id)');
        $this->addSql('ALTER TABLE game_character_event ADD CONSTRAINT FK_4E20C07A1136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_character_event DROP FOREIGN KEY FK_4E20C07A8925311C');
        $this->addSql('ALTER TABLE game_character_event DROP FOREIGN KEY FK_4E20C07A1136BE75');
        $this->addSql('DROP TABLE game_character_event');

        $this->addSql('ALTER TABLE game_character_goal DROP FOREIGN KEY FK_2C61A22D1136BE75');
        $this->addSql('DROP TABLE game_character_goal');
    }
}
