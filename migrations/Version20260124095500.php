<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124095500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preparedSinceTick to LocalActor prepared technique state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local_actor ADD prepared_since_tick int DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local_actor DROP prepared_since_tick');
    }
}

