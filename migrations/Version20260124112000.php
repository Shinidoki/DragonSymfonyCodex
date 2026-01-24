<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add NPC profiles for autonomous simulation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_npc_profile (id int AUTO_INCREMENT NOT NULL, character_id int NOT NULL, archetype varchar(255) NOT NULL, wander_sequence int DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_npc_profile_character (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_npc_profile ADD CONSTRAINT FK_92B94A3B1136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_npc_profile DROP FOREIGN KEY FK_92B94A3B1136BE75');
        $this->addSql('DROP TABLE game_npc_profile');
    }
}

