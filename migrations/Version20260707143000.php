<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track successful Sage exports for paid orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sage_order_export (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, sage_status_code INT DEFAULT 200 NOT NULL, payload LONGTEXT NOT NULL, sage_response LONGTEXT DEFAULT NULL, sent_at DATETIME NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SAGE_ORDER_EXPORT_ORDER (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sage_order_export ADD CONSTRAINT FK_SAGE_ORDER_EXPORT_ORDER FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sage_order_export DROP FOREIGN KEY FK_SAGE_ORDER_EXPORT_ORDER');
        $this->addSql('DROP TABLE sage_order_export');
    }
}
