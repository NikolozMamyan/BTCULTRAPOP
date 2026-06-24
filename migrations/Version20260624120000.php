<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add newsletter subscriptions and track order confirmation emails.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, source VARCHAR(20) DEFAULT \'footer\' NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, subscribed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', unsubscribed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_NEWSLETTER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_order ADD confirmation_email_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_subscriber');
        $this->addSql('ALTER TABLE customer_order DROP confirmation_email_sent_at');
    }
}
