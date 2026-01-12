<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112193733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `character` (id int AUTO_INCREMENT NOT NULL, name varchar(120) NOT NULL, race varchar(255) NOT NULL, age_days int NOT NULL, created_at datetime NOT NULL, strength int NOT NULL, speed int NOT NULL, endurance int NOT NULL, durability int NOT NULL, ki_capacity int NOT NULL, ki_control int NOT NULL, ki_recovery int NOT NULL, focus int NOT NULL, discipline int NOT NULL, adaptability int NOT NULL, world_id int NOT NULL, INDEX idx_937ab0348925311c (world_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE world (id int AUTO_INCREMENT NOT NULL, seed varchar(128) NOT NULL, current_day int NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id bigint AUTO_INCREMENT NOT NULL, body longtext NOT NULL, headers longtext NOT NULL, queue_name varchar(190) NOT NULL, created_at datetime NOT NULL, available_at datetime NOT NULL, delivered_at datetime DEFAULT NULL, INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT FK_937AB0348925311C FOREIGN KEY (world_id) REFERENCES world (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY FK_937AB0348925311C');
        $this->addSql('DROP TABLE `character`');
        $this->addSql('DROP TABLE world');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
