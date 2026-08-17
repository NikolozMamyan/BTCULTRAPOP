<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manually controlled storefront maintenance settings and customer-facing display dates.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('store_settings')) {
            return;
        }

        $this->addSql('CREATE TABLE store_settings (id INT AUTO_INCREMENT NOT NULL, maintenance_enabled TINYINT(1) DEFAULT 0 NOT NULL, maintenance_starts_at DATETIME DEFAULT NULL, maintenance_ends_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO store_settings (id, maintenance_enabled, maintenance_starts_at, maintenance_ends_at, updated_at) VALUES (1, 0, NULL, NULL, CURRENT_TIMESTAMP)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('store_settings')) {
            return;
        }

        $this->addSql('DROP TABLE store_settings');
    }
}
