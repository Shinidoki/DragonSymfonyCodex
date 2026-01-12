<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112222317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE local_combat (id int AUTO_INCREMENT NOT NULL, status varchar(16) NOT NULL, started_at datetime NOT NULL, ended_at datetime DEFAULT NULL, session_id int NOT NULL, UNIQUE INDEX UNIQ_8A23C11D613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE local_combatant (id int AUTO_INCREMENT NOT NULL, actor_id int NOT NULL, max_hp int NOT NULL, current_hp int NOT NULL, defeated_at_tick int DEFAULT NULL, combat_id int NOT NULL, INDEX idx_7e3a2cddfc7eedb8 (combat_id), UNIQUE INDEX uniq_local_combatant_combat_actor (combat_id, actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE local_combat ADD CONSTRAINT FK_8A23C11D613FECDF FOREIGN KEY (session_id) REFERENCES local_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE local_combatant ADD CONSTRAINT FK_7E3A2CDDFC7EEDB8 FOREIGN KEY (combat_id) REFERENCES local_combat (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE local_combat DROP FOREIGN KEY FK_8A23C11D613FECDF');
        $this->addSql('ALTER TABLE local_combatant DROP FOREIGN KEY FK_7E3A2CDDFC7EEDB8');
        $this->addSql('DROP TABLE local_combat');
        $this->addSql('DROP TABLE local_combatant');
    }
}
