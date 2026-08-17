<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link checkout orders to their source cart for safe cart recovery.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order') || !$schema->hasTable('cart')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if ($table->hasColumn('cart_id')) {
            return;
        }

        $this->addSql('ALTER TABLE customer_order ADD cart_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_ORDER_CART FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3B1CE6A31AD5CDBF ON customer_order (cart_id)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if (!$table->hasColumn('cart_id')) {
            return;
        }

        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_ORDER_CART');
        $this->addSql('DROP INDEX IDX_3B1CE6A31AD5CDBF ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP cart_id');
    }
}
