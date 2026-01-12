<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112203303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE local_session (id int AUTO_INCREMENT NOT NULL, world_id int NOT NULL, character_id int NOT NULL, tile_x int NOT NULL, tile_y int NOT NULL, width int NOT NULL, height int NOT NULL, player_x int NOT NULL, player_y int NOT NULL, current_tick int DEFAULT 0 NOT NULL, status varchar(16) NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE local_session');
    }
}
