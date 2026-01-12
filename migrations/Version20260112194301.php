<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112194301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_character (id int AUTO_INCREMENT NOT NULL, name varchar(120) NOT NULL, race varchar(255) NOT NULL, age_days int NOT NULL, created_at datetime NOT NULL, strength int NOT NULL, speed int NOT NULL, endurance int NOT NULL, durability int NOT NULL, ki_capacity int NOT NULL, ki_control int NOT NULL, ki_recovery int NOT NULL, focus int NOT NULL, discipline int NOT NULL, adaptability int NOT NULL, world_id int NOT NULL, INDEX idx_41dc71368925311c (world_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC71368925311C FOREIGN KEY (world_id) REFERENCES world (id)');
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY `FK_937AB0348925311C`');
        $this->addSql('DROP TABLE `character`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `character` (id int AUTO_INCREMENT NOT NULL, name varchar(120) character set utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, race varchar(255) character set utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, age_days int NOT NULL, created_at datetime NOT NULL, strength int NOT NULL, speed int NOT NULL, endurance int NOT NULL, durability int NOT NULL, ki_capacity int NOT NULL, ki_control int NOT NULL, ki_recovery int NOT NULL, focus int NOT NULL, discipline int NOT NULL, adaptability int NOT NULL, world_id int NOT NULL, INDEX idx_937ab0348925311c (world_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT `FK_937AB0348925311C` FOREIGN KEY (world_id) REFERENCES world (id)');
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC71368925311C');
        $this->addSql('DROP TABLE game_character');
    }
}
