<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active stock source settings.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('stock_settings')) {
            $this->addSql('CREATE TABLE stock_settings (id INT AUTO_INCREMENT NOT NULL, active_source VARCHAR(20) DEFAULT \'bureau\' NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('INSERT INTO stock_settings (id, active_source, updated_at) VALUES (1, \'bureau\', NOW())');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('stock_settings')) {
            $this->addSql('DROP TABLE stock_settings');
        }
    }
}
