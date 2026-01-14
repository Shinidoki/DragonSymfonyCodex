<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114202156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE character_transformation (id INT AUTO_INCREMENT NOT NULL, transformation VARCHAR(255) NOT NULL, proficiency INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, character_id INT NOT NULL, INDEX IDX_DB5F62A01136BE75 (character_id), UNIQUE INDEX uniq_character_transformation_character_transformation (character_id, transformation), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE character_transformation ADD CONSTRAINT FK_DB5F62A01136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE character_transformation DROP FOREIGN KEY FK_DB5F62A01136BE75');
        $this->addSql('DROP TABLE character_transformation');
    }
}
