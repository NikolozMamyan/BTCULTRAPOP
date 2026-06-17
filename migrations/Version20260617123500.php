<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617123500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active flag to licenses for admin publication control.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('product_license')) {
            return;
        }

        $table = $schema->getTable('product_license');

        if ($table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE product_license ADD active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('product_license')) {
            return;
        }

        $table = $schema->getTable('product_license');

        if (!$table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE product_license DROP active');
    }
}
