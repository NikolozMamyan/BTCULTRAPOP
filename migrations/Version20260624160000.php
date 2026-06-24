<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align promotion indexes and immutable datetime columns with the current Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart RENAME INDEX IDX_CART_PROMO_CODE TO IDX_BA388B72FAE4625');
        $this->addSql('ALTER TABLE customer_order RENAME INDEX IDX_ORDER_PROMO_CODE TO IDX_3B1CE6A32FAE4625');
        $this->addSql('ALTER TABLE promo_code RENAME INDEX IDX_PROMO_ASSIGNED_USER TO IDX_3D8C939EADF66B1A');
        $this->addSql('ALTER TABLE newsletter_subscriber CHANGE subscribed_at subscribed_at DATETIME NOT NULL, CHANGE unsubscribed_at unsubscribed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order CHANGE confirmation_email_sent_at confirmation_email_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE promo_code CHANGE valid_from valid_from DATETIME DEFAULT NULL, CHANGE valid_until valid_until DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart RENAME INDEX IDX_BA388B72FAE4625 TO IDX_CART_PROMO_CODE');
        $this->addSql('ALTER TABLE customer_order RENAME INDEX IDX_3B1CE6A32FAE4625 TO IDX_ORDER_PROMO_CODE');
        $this->addSql('ALTER TABLE promo_code RENAME INDEX IDX_3D8C939EADF66B1A TO IDX_PROMO_ASSIGNED_USER');
        $this->addSql('ALTER TABLE newsletter_subscriber CHANGE subscribed_at subscribed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE unsubscribed_at unsubscribed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE customer_order CHANGE confirmation_email_sent_at confirmation_email_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE promo_code CHANGE valid_from valid_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE valid_until valid_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
