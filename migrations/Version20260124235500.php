<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tournaments';
    }

    public function up(Schema $schema): void
    {
        // Defensive: local dev DBs may already have tables while migrations table is empty.
        if (!$schema->hasTable('game_tournament')) {
            $this->addSql('CREATE TABLE game_tournament (id int AUTO_INCREMENT NOT NULL, world_id int NOT NULL, settlement_id int NOT NULL, announce_day int NOT NULL, resolve_day int NOT NULL, spend int NOT NULL, prize_pool int NOT NULL, radius int NOT NULL, status varchar(16) NOT NULL, request_event_id int DEFAULT NULL, created_at datetime NOT NULL, UNIQUE INDEX uniq_tournament_request_event (request_event_id), INDEX idx_tournament_world_resolve (world_id, resolve_day, status), INDEX idx_tournament_world (world_id), INDEX idx_tournament_settlement (settlement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE game_tournament ADD CONSTRAINT FK_5E67D6C18925311C FOREIGN KEY (world_id) REFERENCES world (id)');
            $this->addSql('ALTER TABLE game_tournament ADD CONSTRAINT FK_5E67D6C14955B77 FOREIGN KEY (settlement_id) REFERENCES game_settlement (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_tournament DROP FOREIGN KEY FK_5E67D6C18925311C');
        $this->addSql('ALTER TABLE game_tournament DROP FOREIGN KEY FK_5E67D6C14955B77');
        $this->addSql('DROP TABLE game_tournament');
    }
}
