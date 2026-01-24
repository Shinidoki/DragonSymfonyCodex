<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260114221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace LocalActor charging columns with prepared technique state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local_actor ADD prepared_phase varchar(255) DEFAULT NULL, DROP charging_target_actor_id, CHANGE charging_technique_code prepared_technique_code VARCHAR(64) DEFAULT NULL, CHANGE charging_ticks_remaining prepared_ticks_remaining INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local_actor ADD charging_target_actor_id int DEFAULT NULL, DROP prepared_phase, CHANGE prepared_technique_code charging_technique_code VARCHAR(64) DEFAULT NULL, CHANGE prepared_ticks_remaining charging_ticks_remaining INT DEFAULT 0 NOT NULL');
    }
}

