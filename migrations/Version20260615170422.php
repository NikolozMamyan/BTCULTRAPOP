<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615170422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the product catalog, categories, licenses and product images.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_64C19C15E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, seo_title VARCHAR(255) NOT NULL, seo_description VARCHAR(512) NOT NULL, reference VARCHAR(64) NOT NULL, price_tax_excluded NUMERIC(20, 6) NOT NULL, price_tax_included NUMERIC(20, 6) NOT NULL, quantity INT DEFAULT 0 NOT NULL, `condition` VARCHAR(20) NOT NULL, width NUMERIC(10, 3) DEFAULT NULL, height NUMERIC(10, 3) DEFAULT NULL, depth NUMERIC(10, 3) DEFAULT NULL, weight NUMERIC(10, 3) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT NOT NULL, license_id INT NOT NULL, UNIQUE INDEX UNIQ_D34A04ADAEA34913 (reference), INDEX IDX_D34A04AD12469DE2 (category_id), INDEX IDX_D34A04AD460F904B (license_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(2048) NOT NULL, alt VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, cover TINYINT DEFAULT 0 NOT NULL, product_id INT NOT NULL, INDEX IDX_64617F034584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_license (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_86B1EFA95E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD460F904B FOREIGN KEY (license_id) REFERENCES product_license (id)');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD460F904B');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product_license');
    }
}
