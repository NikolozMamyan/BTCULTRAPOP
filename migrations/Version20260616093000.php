<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer avatar and loyalty points.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD avatar_filename VARCHAR(255) DEFAULT NULL, ADD loyalty_points INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP avatar_filename, DROP loyalty_points');
    }
}
