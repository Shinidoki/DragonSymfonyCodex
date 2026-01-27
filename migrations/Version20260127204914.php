<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127204914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_settlement_building ADD master_last_challenged_day int DEFAULT -1 NOT NULL, ADD master_character_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_settlement_building ADD CONSTRAINT FK_C7406E898CEEC16A FOREIGN KEY (master_character_id) REFERENCES game_character (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C7406E898CEEC16A ON game_settlement_building (master_character_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_settlement_building DROP FOREIGN KEY FK_C7406E898CEEC16A');
        $this->addSql('DROP INDEX IDX_C7406E898CEEC16A ON game_settlement_building');
        $this->addSql('ALTER TABLE game_settlement_building DROP master_last_challenged_day, DROP master_character_id');
    }
}
