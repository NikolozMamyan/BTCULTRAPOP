<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first and last names to users while keeping address names separate.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL');
        $this->addSql("UPDATE app_user SET first_name = '' WHERE first_name IS NULL");
        $this->addSql("UPDATE app_user SET last_name = '' WHERE last_name IS NULL");
        $this->addSql('ALTER TABLE app_user CHANGE first_name first_name VARCHAR(100) NOT NULL, CHANGE last_name last_name VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP first_name, DROP last_name');
    }
}
