<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112210408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE local_actor (id int AUTO_INCREMENT NOT NULL, character_id int NOT NULL, role varchar(16) NOT NULL, x int NOT NULL, y int NOT NULL, created_at datetime NOT NULL, session_id int NOT NULL, INDEX idx_5b93cafd613fecdf (session_id), UNIQUE INDEX uniq_local_actor_session_character (session_id, character_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE local_actor ADD CONSTRAINT FK_5B93CAFD613FECDF FOREIGN KEY (session_id) REFERENCES local_session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE local_actor DROP FOREIGN KEY FK_5B93CAFD613FECDF');
        $this->addSql('DROP TABLE local_actor');
    }
}
