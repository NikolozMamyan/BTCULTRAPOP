<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store tax-exclusive shipping and discount snapshots on customer orders.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if (!$table->hasColumn('shipping_amount_tax_excluded_cents')) {
            $this->addSql('ALTER TABLE customer_order ADD shipping_amount_tax_excluded_cents INT DEFAULT NULL');
        }

        if (!$table->hasColumn('discount_amount_tax_excluded_cents')) {
            $this->addSql('ALTER TABLE customer_order ADD discount_amount_tax_excluded_cents INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if ($table->hasColumn('shipping_amount_tax_excluded_cents')) {
            $this->addSql('ALTER TABLE customer_order DROP shipping_amount_tax_excluded_cents');
        }

        if ($table->hasColumn('discount_amount_tax_excluded_cents')) {
            $this->addSql('ALTER TABLE customer_order DROP discount_amount_tax_excluded_cents');
        }
    }
}
