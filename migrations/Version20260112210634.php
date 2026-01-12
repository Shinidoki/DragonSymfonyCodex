<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112210634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE local_intent (id int AUTO_INCREMENT NOT NULL, type varchar(255) NOT NULL, target_actor_id int DEFAULT NULL, created_at datetime NOT NULL, actor_id int NOT NULL, INDEX idx_b91ed99410daf24a (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE local_intent ADD CONSTRAINT FK_B91ED99410DAF24A FOREIGN KEY (actor_id) REFERENCES local_actor (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE local_intent DROP FOREIGN KEY FK_B91ED99410DAF24A');
        $this->addSql('DROP TABLE local_intent');
    }
}
