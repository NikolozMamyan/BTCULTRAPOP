<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617103500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize user favorite schema names for Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user_favorite')) {
            return;
        }

        $table = $schema->getTable('user_favorite');

        $this->addSql('ALTER TABLE user_favorite CHANGE created_at created_at DATETIME NOT NULL');

        if ($table->hasIndex('idx_8e11ebe3a76ed395')) {
            $this->addSql('ALTER TABLE user_favorite RENAME INDEX idx_8e11ebe3a76ed395 TO IDX_88486AD9A76ED395');
        }

        if ($table->hasIndex('idx_8e11ebe34584665a')) {
            $this->addSql('ALTER TABLE user_favorite RENAME INDEX idx_8e11ebe34584665a TO IDX_88486AD94584665A');
        }
    }

    public function down(Schema $schema): void
    {
        // This migration only normalizes names and column metadata.
    }
}
