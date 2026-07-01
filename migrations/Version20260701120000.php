<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password reset tokens for customer account recovery.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('password_reset_token')) {
            return;
        }

        $this->addSql('CREATE TABLE password_reset_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(32) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_PASSWORD_RESET_SELECTOR (selector), INDEX IDX_PASSWORD_RESET_EXPIRES_AT (expires_at), INDEX IDX_FC5D6E50A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_FC5D6E50A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('password_reset_token')) {
            return;
        }

        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_FC5D6E50A76ED395');
        $this->addSql('DROP TABLE password_reset_token');
    }
}
