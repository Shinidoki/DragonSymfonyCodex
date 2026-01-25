<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125003151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('game_tournament_participant')) {
            $this->addSql('CREATE TABLE game_tournament_participant (id int AUTO_INCREMENT NOT NULL, status varchar(16) NOT NULL, registered_day int NOT NULL, eliminated_day int DEFAULT NULL, seed int DEFAULT NULL, final_rank int DEFAULT NULL, created_at datetime NOT NULL, tournament_id int NOT NULL, character_id int NOT NULL, INDEX idx_tournament_participant_tournament (tournament_id), INDEX idx_tournament_participant_character (character_id), UNIQUE INDEX uniq_tournament_participant (tournament_id, character_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE game_tournament_participant ADD CONSTRAINT FK_B53ABD1033D1A3E7 FOREIGN KEY (tournament_id) REFERENCES game_tournament (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE game_tournament_participant ADD CONSTRAINT FK_B53ABD101136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
        }

        if ($schema->hasTable('game_settlement')) {
            $table = $schema->getTable('game_settlement');
            if ($table->hasIndex('idx_settlement_world') && !$table->hasIndex('IDX_3343DCBC8925311C')) {
                $this->addSql('ALTER TABLE game_settlement RENAME INDEX idx_settlement_world TO IDX_3343DCBC8925311C');
            }
        }

        if ($schema->hasTable('game_tournament')) {
            $table = $schema->getTable('game_tournament');
            if ($table->hasIndex('idx_tournament_world') && !$table->hasIndex('IDX_53837F348925311C')) {
                $this->addSql('ALTER TABLE game_tournament RENAME INDEX idx_tournament_world TO IDX_53837F348925311C');
            }
            if ($table->hasIndex('idx_tournament_settlement') && !$table->hasIndex('IDX_53837F34C2B9C425')) {
                $this->addSql('ALTER TABLE game_tournament RENAME INDEX idx_tournament_settlement TO IDX_53837F34C2B9C425');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('game_tournament_participant')) {
            $this->addSql('ALTER TABLE game_tournament_participant DROP FOREIGN KEY FK_B53ABD1033D1A3E7');
            $this->addSql('ALTER TABLE game_tournament_participant DROP FOREIGN KEY FK_B53ABD101136BE75');
            $this->addSql('DROP TABLE game_tournament_participant');
        }

        if ($schema->hasTable('game_settlement')) {
            $table = $schema->getTable('game_settlement');
            if ($table->hasIndex('IDX_3343DCBC8925311C') && !$table->hasIndex('idx_settlement_world')) {
                $this->addSql('ALTER TABLE game_settlement RENAME INDEX IDX_3343DCBC8925311C TO idx_settlement_world');
            }
        }

        if ($schema->hasTable('game_tournament')) {
            $table = $schema->getTable('game_tournament');
            if ($table->hasIndex('IDX_53837F34C2B9C425') && !$table->hasIndex('idx_tournament_settlement')) {
                $this->addSql('ALTER TABLE game_tournament RENAME INDEX IDX_53837F34C2B9C425 TO idx_tournament_settlement');
            }
            if ($table->hasIndex('IDX_53837F348925311C') && !$table->hasIndex('idx_tournament_world')) {
                $this->addSql('ALTER TABLE game_tournament RENAME INDEX IDX_53837F348925311C TO idx_tournament_world');
            }
        }
    }
}
