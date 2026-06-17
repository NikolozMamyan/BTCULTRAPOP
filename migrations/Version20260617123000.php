<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active flag to categories for admin publication control.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('category')) {
            return;
        }

        $table = $schema->getTable('category');

        if ($table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE category ADD active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('category')) {
            return;
        }

        $table = $schema->getTable('category');

        if (!$table->hasColumn('active')) {
            return;
        }

        $this->addSql('ALTER TABLE category DROP active');
    }
}
