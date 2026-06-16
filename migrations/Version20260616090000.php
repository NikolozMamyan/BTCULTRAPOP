<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create persistent user sessions for custom cookie authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_session (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(64) NOT NULL, token_hash VARCHAR(64) NOT NULL, device_name VARCHAR(80) NOT NULL, user_agent_hash VARCHAR(64) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, absolute_expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8849CBDE9692E25D (selector), INDEX IDX_8849CBDEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDEA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDEA76ED395');
        $this->addSql('DROP TABLE user_session');
    }
}
