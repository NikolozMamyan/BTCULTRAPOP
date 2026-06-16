<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cart and order tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(64) DEFAULT NULL, status VARCHAR(20) NOT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_BA388B75F37A13B (token), INDEX IDX_BA388B7A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, product_id INT NOT NULL, quantity INT DEFAULT 1 NOT NULL, unit_price_tax_excluded_cents INT DEFAULT 0 NOT NULL, unit_price_tax_included_cents INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE25274584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer_order (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, order_number VARCHAR(32) NOT NULL, status VARCHAR(30) NOT NULL, payment_status VARCHAR(20) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, total_tax_excluded_cents INT DEFAULT 0 NOT NULL, total_tax_included_cents INT DEFAULT 0 NOT NULL, shipping_amount_tax_included_cents INT DEFAULT 0 NOT NULL, discount_amount_tax_included_cents INT DEFAULT 0 NOT NULL, loyalty_points_earned INT DEFAULT 0 NOT NULL, customer_email VARCHAR(180) NOT NULL, customer_name VARCHAR(201) NOT NULL, shipping_name VARCHAR(100) NOT NULL, shipping_street VARCHAR(255) NOT NULL, shipping_postal_code VARCHAR(20) NOT NULL, shipping_city VARCHAR(120) NOT NULL, shipping_country_code VARCHAR(2) DEFAULT \'FR\' NOT NULL, shipping_phone VARCHAR(30) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3B1CE6A3551F0F81 (order_number), INDEX IDX_3B1CE6A3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, product_id INT DEFAULT NULL, product_name VARCHAR(255) NOT NULL, product_reference VARCHAR(64) DEFAULT NULL, product_ean VARCHAR(13) DEFAULT NULL, product_image VARCHAR(2048) DEFAULT NULL, category_name VARCHAR(128) DEFAULT NULL, license_name VARCHAR(128) DEFAULT NULL, quantity INT DEFAULT 1 NOT NULL, unit_price_tax_excluded_cents INT DEFAULT 0 NOT NULL, unit_price_tax_included_cents INT DEFAULT 0 NOT NULL, tax_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, total_tax_excluded_cents INT DEFAULT 0 NOT NULL, total_tax_included_cents INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_CART_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_CART_ITEM_CART FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_CART_ITEM_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_CUSTOMER_ORDER_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_ORDER FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_ORDER_ITEM_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_ORDER_ITEM_ORDER');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_ORDER_ITEM_PRODUCT');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_CUSTOMER_ORDER_USER');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_CART_ITEM_CART');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_CART_ITEM_PRODUCT');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_CART_USER');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE cart');
    }
}
