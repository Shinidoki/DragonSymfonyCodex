<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112204803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE local_event (id int AUTO_INCREMENT NOT NULL, tick int NOT NULL, x int NOT NULL, y int NOT NULL, message longtext NOT NULL, created_at datetime NOT NULL, session_id int NOT NULL, INDEX idx_244896a3613fecdf (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE local_event ADD CONSTRAINT FK_244896A3613FECDF FOREIGN KEY (session_id) REFERENCES local_session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE local_event DROP FOREIGN KEY FK_244896A3613FECDF');
        $this->addSql('DROP TABLE local_event');
    }
}
