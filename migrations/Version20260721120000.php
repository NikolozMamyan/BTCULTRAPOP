<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product or delivery targets to promo codes and configurable visitor popup settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE promo_code ADD application_type VARCHAR(20) DEFAULT 'products' NOT NULL");
        $this->addSql('CREATE TABLE popup_settings (id INT AUTO_INCREMENT NOT NULL, promo_code_id INT DEFAULT NULL, active TINYINT(1) DEFAULT 0 NOT NULL, title VARCHAR(120) NOT NULL, message VARCHAR(500) NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_62ADCE3F2FAE4625 (promo_code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE popup_settings ADD CONSTRAINT FK_POPUP_SETTINGS_PROMO_CODE FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE popup_settings DROP FOREIGN KEY FK_POPUP_SETTINGS_PROMO_CODE');
        $this->addSql('DROP TABLE popup_settings');
        $this->addSql('ALTER TABLE promo_code DROP application_type');
    }
}
