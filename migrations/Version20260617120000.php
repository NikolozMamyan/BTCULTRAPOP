<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active flag to products for storefront publication control.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('product')) {
            return;
        }

        $table = $schema->getTable('product');

        if ($table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE product ADD active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('product')) {
            return;
        }

        $table = $schema->getTable('product');

        if (!$table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE product DROP active');
    }
}
