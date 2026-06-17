<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe payment settings, webhook dedupe and checkout metadata on orders.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('payment_settings')) {
            $this->addSql("CREATE TABLE payment_settings (id INT AUTO_INCREMENT NOT NULL, stripe_mode VARCHAR(16) DEFAULT 'sandbox' NOT NULL, updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
            $this->addSql("INSERT INTO payment_settings (id, stripe_mode, updated_at) VALUES (1, 'sandbox', CURRENT_TIMESTAMP)");
        }

        if (!$schema->hasTable('stripe_webhook_event')) {
            $this->addSql("CREATE TABLE stripe_webhook_event (id INT AUTO_INCREMENT NOT NULL, event_id VARCHAR(255) NOT NULL, type VARCHAR(120) NOT NULL, processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_STRIPE_WEBHOOK_EVENT_ID (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }

        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $orderTable = $schema->getTable('customer_order');

        if ($orderTable->hasColumn('customer_email')) {
            $this->addSql('ALTER TABLE customer_order CHANGE customer_email customer_email VARCHAR(180) DEFAULT NULL');
        }

        if (!$orderTable->hasColumn('stripe_checkout_session_id')) {
            $this->addSql('ALTER TABLE customer_order ADD stripe_checkout_session_id VARCHAR(255) DEFAULT NULL');
        }

        if (!$orderTable->hasColumn('stripe_payment_intent_id')) {
            $this->addSql('ALTER TABLE customer_order ADD stripe_payment_intent_id VARCHAR(255) DEFAULT NULL');
        }

        if (!$orderTable->hasColumn('stripe_customer_id')) {
            $this->addSql('ALTER TABLE customer_order ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        }

        if (!$orderTable->hasColumn('payment_failure_reason')) {
            $this->addSql('ALTER TABLE customer_order ADD payment_failure_reason VARCHAR(255) DEFAULT NULL');
        }

        if (!$orderTable->hasIndex('UNIQ_ORDER_STRIPE_SESSION')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDER_STRIPE_SESSION ON customer_order (stripe_checkout_session_id)');
        }

        if (!$orderTable->hasIndex('IDX_ORDER_STRIPE_INTENT')) {
            $this->addSql('CREATE INDEX IDX_ORDER_STRIPE_INTENT ON customer_order (stripe_payment_intent_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('customer_order')) {
            $orderTable = $schema->getTable('customer_order');

            if ($orderTable->hasIndex('UNIQ_ORDER_STRIPE_SESSION')) {
                $this->addSql('DROP INDEX UNIQ_ORDER_STRIPE_SESSION ON customer_order');
            }

            if ($orderTable->hasIndex('IDX_ORDER_STRIPE_INTENT')) {
                $this->addSql('DROP INDEX IDX_ORDER_STRIPE_INTENT ON customer_order');
            }

            if ($orderTable->hasColumn('payment_failure_reason')) {
                $this->addSql('ALTER TABLE customer_order DROP payment_failure_reason');
            }

            if ($orderTable->hasColumn('stripe_customer_id')) {
                $this->addSql('ALTER TABLE customer_order DROP stripe_customer_id');
            }

            if ($orderTable->hasColumn('stripe_payment_intent_id')) {
                $this->addSql('ALTER TABLE customer_order DROP stripe_payment_intent_id');
            }

            if ($orderTable->hasColumn('stripe_checkout_session_id')) {
                $this->addSql('ALTER TABLE customer_order DROP stripe_checkout_session_id');
            }

            if ($orderTable->hasColumn('customer_email')) {
                $this->addSql("UPDATE customer_order SET customer_email = CONCAT('client+', id, '@ultrapop.local') WHERE customer_email IS NULL");
                $this->addSql('ALTER TABLE customer_order CHANGE customer_email customer_email VARCHAR(180) NOT NULL');
            }
        }

        if ($schema->hasTable('stripe_webhook_event')) {
            $this->addSql('DROP TABLE stripe_webhook_event');
        }

        if ($schema->hasTable('payment_settings')) {
            $this->addSql('DROP TABLE payment_settings');
        }
    }
}
