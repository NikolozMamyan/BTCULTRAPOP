<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promotional codes, cart assignment, order snapshots and usage reservation tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, assigned_user_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, discount_type VARCHAR(20) NOT NULL, value NUMERIC(10, 2) NOT NULL, valid_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', valid_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', max_uses INT DEFAULT NULL, used_count INT DEFAULT 0 NOT NULL, reserved_count INT DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_PROMO_CODE (code), INDEX IDX_PROMO_ASSIGNED_USER (assigned_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT FK_PROMO_ASSIGNED_USER FOREIGN KEY (assigned_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cart ADD promo_code_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_CART_PROMO_CODE FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CART_PROMO_CODE ON cart (promo_code_id)');
        $this->addSql('ALTER TABLE customer_order ADD promo_code_id INT DEFAULT NULL, ADD promo_code_snapshot VARCHAR(50) DEFAULT NULL, ADD promo_reservation_active TINYINT(1) DEFAULT 0 NOT NULL, ADD promo_usage_recorded TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_ORDER_PROMO_CODE FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ORDER_PROMO_CODE ON customer_order (promo_code_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_CART_PROMO_CODE');
        $this->addSql('DROP INDEX IDX_CART_PROMO_CODE ON cart');
        $this->addSql('ALTER TABLE cart DROP promo_code_id');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_ORDER_PROMO_CODE');
        $this->addSql('DROP INDEX IDX_ORDER_PROMO_CODE ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP promo_code_id, DROP promo_code_snapshot, DROP promo_reservation_active, DROP promo_usage_recorded');
        $this->addSql('DROP TABLE promo_code');
    }
}
