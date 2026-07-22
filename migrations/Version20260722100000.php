<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add configurable delivery tiers with a ten euro minimum order and delivery starting at six euros.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shipping_settings (id INT AUTO_INCREMENT NOT NULL, minimum_order_cents INT DEFAULT 1000 NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shipping_rate_tier (id INT AUTO_INCREMENT NOT NULL, settings_id INT NOT NULL, threshold_cents INT NOT NULL, shipping_amount_cents INT NOT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_SHIPPING_TIER_SETTINGS (settings_id), INDEX IDX_SHIPPING_TIER_POSITION (settings_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shipping_rate_tier ADD CONSTRAINT FK_SHIPPING_TIER_SETTINGS FOREIGN KEY (settings_id) REFERENCES shipping_settings (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO shipping_settings (id, minimum_order_cents, updated_at) VALUES (1, 1000, CURRENT_TIMESTAMP)");
        $this->addSql('INSERT INTO shipping_rate_tier (settings_id, threshold_cents, shipping_amount_cents, position) VALUES (1, 1000, 600, 0), (1, 2000, 475, 1), (1, 3000, 350, 2), (1, 4000, 250, 3), (1, 5000, 0, 4)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_rate_tier DROP FOREIGN KEY FK_SHIPPING_TIER_SETTINGS');
        $this->addSql('DROP TABLE shipping_rate_tier');
        $this->addSql('DROP TABLE shipping_settings');
    }
}
