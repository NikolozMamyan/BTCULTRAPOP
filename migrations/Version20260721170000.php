<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track whether an active visitor viewed and copied the promotional popup code.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site_visitor')) {
            return;
        }

        $table = $schema->getTable('site_visitor');

        if (!$table->hasColumn('popup_promo_code')) {
            $this->addSql('ALTER TABLE site_visitor ADD popup_promo_code VARCHAR(50) DEFAULT NULL');
        }

        if (!$table->hasColumn('popup_promo_version')) {
            $this->addSql('ALTER TABLE site_visitor ADD popup_promo_version VARCHAR(40) DEFAULT NULL');
        }

        if (!$table->hasColumn('popup_promo_viewed_at')) {
            $this->addSql('ALTER TABLE site_visitor ADD popup_promo_viewed_at DATETIME DEFAULT NULL');
        }

        if (!$table->hasColumn('popup_promo_copied_at')) {
            $this->addSql('ALTER TABLE site_visitor ADD popup_promo_copied_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('site_visitor')) {
            return;
        }

        $table = $schema->getTable('site_visitor');

        if ($table->hasColumn('popup_promo_copied_at')) {
            $this->addSql('ALTER TABLE site_visitor DROP popup_promo_copied_at');
        }

        if ($table->hasColumn('popup_promo_viewed_at')) {
            $this->addSql('ALTER TABLE site_visitor DROP popup_promo_viewed_at');
        }

        if ($table->hasColumn('popup_promo_version')) {
            $this->addSql('ALTER TABLE site_visitor DROP popup_promo_version');
        }

        if ($table->hasColumn('popup_promo_code')) {
            $this->addSql('ALTER TABLE site_visitor DROP popup_promo_code');
        }
    }
}
