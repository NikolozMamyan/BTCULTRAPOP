<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track payment reminder sends on customer orders.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if (!$table->hasColumn('payment_reminder_sent_at')) {
            $this->addSql('ALTER TABLE customer_order ADD payment_reminder_sent_at DATETIME DEFAULT NULL');
        }

        if (!$table->hasColumn('payment_reminder_count')) {
            $this->addSql('ALTER TABLE customer_order ADD payment_reminder_count INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $table = $schema->getTable('customer_order');

        if ($table->hasColumn('payment_reminder_sent_at')) {
            $this->addSql('ALTER TABLE customer_order DROP payment_reminder_sent_at');
        }

        if ($table->hasColumn('payment_reminder_count')) {
            $this->addSql('ALTER TABLE customer_order DROP payment_reminder_count');
        }
    }
}
