<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track prepared quantities and move paid orders into the preparation workflow.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('order_item')) {
            $table = $schema->getTable('order_item');

            if (!$table->hasColumn('prepared_quantity')) {
                $this->addSql('ALTER TABLE order_item ADD prepared_quantity INT DEFAULT 0 NOT NULL');
            }
        }

        if ($schema->hasTable('customer_order') && $schema->hasTable('sage_order_export')) {
            $this->addSql("UPDATE customer_order o INNER JOIN sage_order_export s ON s.order_id = o.id SET o.status = 'prepared' WHERE o.payment_status = 'paid' AND o.status IN ('paid', 'preparation')");
        }

        if ($schema->hasTable('order_item') && $schema->hasTable('sage_order_export')) {
            $this->addSql('UPDATE order_item i INNER JOIN sage_order_export s ON s.order_id = i.order_id SET i.prepared_quantity = i.quantity');
        }

        if ($schema->hasTable('customer_order')) {
            $this->addSql("UPDATE customer_order SET status = 'preparation' WHERE payment_status = 'paid' AND status = 'paid'");
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('order_item') && $schema->getTable('order_item')->hasColumn('prepared_quantity')) {
            $this->addSql('ALTER TABLE order_item DROP prepared_quantity');
        }
    }
}
