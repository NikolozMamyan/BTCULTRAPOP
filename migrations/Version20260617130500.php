<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize Stripe payment schema metadata after payment tables creation.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('payment_settings') && $schema->getTable('payment_settings')->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE payment_settings CHANGE updated_at updated_at DATETIME NOT NULL');
        }

        if ($schema->hasTable('stripe_webhook_event')) {
            $table = $schema->getTable('stripe_webhook_event');

            if ($table->hasColumn('processed_at') && $table->hasColumn('created_at')) {
                $this->addSql('ALTER TABLE stripe_webhook_event CHANGE processed_at processed_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('payment_settings') && $schema->getTable('payment_settings')->hasColumn('updated_at')) {
            $this->addSql("ALTER TABLE payment_settings CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }

        if ($schema->hasTable('stripe_webhook_event')) {
            $table = $schema->getTable('stripe_webhook_event');

            if ($table->hasColumn('processed_at') && $table->hasColumn('created_at')) {
                $this->addSql("ALTER TABLE stripe_webhook_event CHANGE processed_at processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
            }
        }
    }
}
