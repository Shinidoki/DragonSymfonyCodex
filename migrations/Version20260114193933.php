<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114193933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE character_technique (id INT AUTO_INCREMENT NOT NULL, proficiency INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, character_id INT NOT NULL, technique_id INT NOT NULL, INDEX IDX_506B3A7A1136BE75 (character_id), INDEX IDX_506B3A7A1F8ACB26 (technique_id), UNIQUE INDEX uniq_character_technique_character_technique (character_id, technique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE character_technique ADD CONSTRAINT FK_506B3A7A1136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE character_technique ADD CONSTRAINT FK_506B3A7A1F8ACB26 FOREIGN KEY (technique_id) REFERENCES technique_definition (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE character_technique DROP FOREIGN KEY FK_506B3A7A1136BE75');
        $this->addSql('ALTER TABLE character_technique DROP FOREIGN KEY FK_506B3A7A1F8ACB26');
        $this->addSql('DROP TABLE character_technique');
    }
}
