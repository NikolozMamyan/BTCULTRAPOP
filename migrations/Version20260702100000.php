<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bureau and clic stock source tables.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('stock_bureau')) {
            $this->addSql('CREATE TABLE stock_bureau (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, quantity INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_STOCK_BUREAU_PRODUCT (product_id), INDEX IDX_STOCK_BUREAU_UPDATED_AT (updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE stock_bureau ADD CONSTRAINT FK_STOCK_BUREAU_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('stock_clic')) {
            $this->addSql('CREATE TABLE stock_clic (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, quantity INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_STOCK_CLIC_PRODUCT (product_id), INDEX IDX_STOCK_CLIC_UPDATED_AT (updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE stock_clic ADD CONSTRAINT FK_STOCK_CLIC_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }

        $this->addSql('INSERT INTO stock_bureau (product_id, quantity, created_at, updated_at)
            SELECT p.id, p.quantity, NOW(), NOW()
            FROM product p
            LEFT JOIN stock_bureau sb ON sb.product_id = p.id
            WHERE sb.product_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('stock_clic')) {
            $this->addSql('ALTER TABLE stock_clic DROP FOREIGN KEY FK_STOCK_CLIC_PRODUCT');
            $this->addSql('DROP TABLE stock_clic');
        }

        if ($schema->hasTable('stock_bureau')) {
            $this->addSql('ALTER TABLE stock_bureau DROP FOREIGN KEY FK_STOCK_BUREAU_PRODUCT');
            $this->addSql('DROP TABLE stock_bureau');
        }
    }
}
