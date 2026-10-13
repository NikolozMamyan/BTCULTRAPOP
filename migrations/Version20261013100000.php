<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store visitor IP addresses, request history and manual IP blocks.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('site_visitor') && !$schema->getTable('site_visitor')->hasColumn('ip_address')) {
            $this->addSql('ALTER TABLE site_visitor ADD ip_address VARCHAR(45) DEFAULT NULL');
        }

        if (!$schema->hasTable('visitor_ip_block')) {
            $this->addSql('CREATE TABLE visitor_ip_block (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, blocked_at DATETIME NOT NULL, blocked_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_VISITOR_IP_BLOCK_ADDRESS (ip_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE visitor_ip_block ADD CONSTRAINT FK_VISITOR_IP_BLOCK_ADMIN FOREIGN KEY (blocked_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('site_visitor_event')) {
            $this->addSql('CREATE TABLE site_visitor_event (id BIGINT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, event_type VARCHAR(32) NOT NULL, request_path VARCHAR(255) NOT NULL, route_name VARCHAR(120) DEFAULT NULL, request_method VARCHAR(10) NOT NULL, http_status SMALLINT UNSIGNED DEFAULT NULL, actor_id INT DEFAULT NULL, occurred_at DATETIME NOT NULL, INDEX IDX_VISITOR_EVENT_IP_ID (ip_address, id), INDEX IDX_VISITOR_EVENT_OCCURRED (occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE site_visitor_event ADD CONSTRAINT FK_VISITOR_EVENT_ACTOR FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('site_visitor_event')) {
            $this->addSql('DROP TABLE site_visitor_event');
        }

        if ($schema->hasTable('visitor_ip_block')) {
            $this->addSql('DROP TABLE visitor_ip_block');
        }

        if ($schema->hasTable('site_visitor') && $schema->getTable('site_visitor')->hasColumn('ip_address')) {
            $this->addSql('ALTER TABLE site_visitor DROP ip_address');
        }
    }
}
