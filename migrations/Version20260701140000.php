<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visitor presence tracking and abandoned cart recovery history.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site_visitor')) {
            $this->addSql('CREATE TABLE site_visitor (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, cart_id INT DEFAULT NULL, visitor_token VARCHAR(64) NOT NULL, visitor_type VARCHAR(20) NOT NULL, ip_hash VARCHAR(64) DEFAULT NULL, user_agent_hash VARCHAR(64) DEFAULT NULL, device_name VARCHAR(120) NOT NULL, current_path VARCHAR(255) NOT NULL, current_route VARCHAR(120) DEFAULT NULL, referer VARCHAR(255) DEFAULT NULL, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SITE_VISITOR_TOKEN (visitor_token), INDEX IDX_SITE_VISITOR_USER (user_id), INDEX IDX_SITE_VISITOR_CART (cart_id), INDEX IDX_SITE_VISITOR_LAST_SEEN (last_seen_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE site_visitor ADD CONSTRAINT FK_SITE_VISITOR_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE site_visitor ADD CONSTRAINT FK_SITE_VISITOR_CART FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('cart_recovery')) {
            $this->addSql('CREATE TABLE cart_recovery (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, email VARCHAR(180) NOT NULL, recovery_token VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, sent_at DATETIME DEFAULT NULL, clicked_at DATETIME DEFAULT NULL, converted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CART_RECOVERY_TOKEN (recovery_token), INDEX IDX_CART_RECOVERY_CART (cart_id), INDEX IDX_CART_RECOVERY_STATUS (status), INDEX IDX_CART_RECOVERY_SENT_AT (sent_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE cart_recovery ADD CONSTRAINT FK_CART_RECOVERY_CART FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('cart_recovery')) {
            $this->addSql('ALTER TABLE cart_recovery DROP FOREIGN KEY FK_CART_RECOVERY_CART');
            $this->addSql('DROP TABLE cart_recovery');
        }

        if ($schema->hasTable('site_visitor')) {
            $this->addSql('ALTER TABLE site_visitor DROP FOREIGN KEY FK_SITE_VISITOR_USER');
            $this->addSql('ALTER TABLE site_visitor DROP FOREIGN KEY FK_SITE_VISITOR_CART');
            $this->addSql('DROP TABLE site_visitor');
        }
    }
}
