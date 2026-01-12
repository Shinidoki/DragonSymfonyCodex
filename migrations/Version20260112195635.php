<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112195635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE world_map_tile (id int AUTO_INCREMENT NOT NULL, x int NOT NULL, y int NOT NULL, biome varchar(255) NOT NULL, has_settlement tinyint DEFAULT 0 NOT NULL, world_id int NOT NULL, INDEX idx_10478fe88925311c (world_id), UNIQUE INDEX uniq_world_xy (world_id, x, y), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE world_map_tile ADD CONSTRAINT FK_10478FE88925311C FOREIGN KEY (world_id) REFERENCES world (id)');
        $this->addSql('ALTER TABLE game_character ADD tile_x int DEFAULT 0 NOT NULL, ADD tile_y INT DEFAULT 0 NOT NULL, ADD target_tile_x INT DEFAULT NULL, ADD target_tile_y INT DEFAULT NULL');
        $this->addSql('ALTER TABLE world ADD planet_name varchar(64) NOT NULL, ADD width INT DEFAULT 0 NOT NULL, ADD height INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE world_map_tile DROP FOREIGN KEY FK_10478FE88925311C');
        $this->addSql('DROP TABLE world_map_tile');
        $this->addSql('ALTER TABLE game_character DROP tile_x, DROP tile_y, DROP target_tile_x, DROP target_tile_y');
        $this->addSql('ALTER TABLE world DROP planet_name, DROP width, DROP height');
    }
}
