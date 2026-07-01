<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reusable admin email templates for customer emailing.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('email_template')) {
            return;
        }

        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, subject VARCHAR(180) NOT NULL, html_content LONGTEXT NOT NULL, audience VARCHAR(40) NOT NULL, recipient_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, INDEX IDX_EMAIL_TEMPLATE_CREATED_AT (created_at), INDEX IDX_9C5B9F7BB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C5B9F7BB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('email_template')) {
            return;
        }

        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C5B9F7BB03A8386');
        $this->addSql('DROP TABLE email_template');
    }
}
