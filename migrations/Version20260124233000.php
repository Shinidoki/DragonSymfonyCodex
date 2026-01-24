<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add settlement economy entities and character economy fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_settlement (id int AUTO_INCREMENT NOT NULL, world_id int NOT NULL, x int NOT NULL, y int NOT NULL, prosperity int DEFAULT 50 NOT NULL, treasury int DEFAULT 0 NOT NULL, fame int DEFAULT 0 NOT NULL, last_sim_day_applied int DEFAULT -1 NOT NULL, created_at datetime_immutable NOT NULL, UNIQUE INDEX uniq_settlement_world_xy (world_id, x, y), INDEX idx_settlement_world (world_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_settlement ADD CONSTRAINT FK_2BAF94128925311C FOREIGN KEY (world_id) REFERENCES world (id)');

        $this->addSql('ALTER TABLE game_character ADD money int DEFAULT 0 NOT NULL, ADD influence INT DEFAULT 0 NOT NULL, ADD work_focus INT DEFAULT 50 NOT NULL, ADD employment_job_code VARCHAR(64) DEFAULT NULL, ADD employment_settlement_x INT DEFAULT NULL, ADD employment_settlement_y INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_settlement DROP FOREIGN KEY FK_2BAF94128925311C');
        $this->addSql('DROP TABLE game_settlement');

        $this->addSql('ALTER TABLE game_character DROP money, DROP influence, DROP work_focus, DROP employment_job_code, DROP employment_settlement_x, DROP employment_settlement_y');
    }
}

