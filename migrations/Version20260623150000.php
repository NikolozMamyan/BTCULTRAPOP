<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add published product reviews and seed two clearly identified editorial reviews per active product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_review (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, author_name VARCHAR(100) NOT NULL, title VARCHAR(160) DEFAULT NULL, content LONGTEXT NOT NULL, rating SMALLINT NOT NULL, editorial TINYINT DEFAULT 0 NOT NULL, published TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_1B3FC0624584665A (product_id), INDEX IDX_PRODUCT_REVIEW_PUBLISHED (published), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_PRODUCT_REVIEW_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            INSERT INTO product_review (product_id, author_name, title, content, rating, editorial, published, created_at)
            SELECT
                product.id,
                'Sélection ULTRAPOP',
                'Un univers bien mis en valeur',
                CONCAT(
                    'Le visuel de ',
                    product.name,
                    ' met immédiatement son univers en valeur. Une référence originale qui associe gourmandise et pop culture avec une identité forte.'
                ),
                5,
                1,
                1,
                CURRENT_TIMESTAMP - INTERVAL 3 DAY
            FROM product
            WHERE product.active = 1
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO product_review (product_id, author_name, title, content, rating, editorial, published, created_at)
            SELECT
                product.id,
                'Comité dégustation ULTRAPOP',
                'Une découverte pop et gourmande',
                CONCAT(
                    'Une découverte agréable, avec un format facile à partager et une licence ',
                    product_license.name,
                    ' bien reconnaissable. Le produit trouve naturellement sa place dans une sélection dédiée aux fans.'
                ),
                4,
                1,
                1,
                CURRENT_TIMESTAMP - INTERVAL 11 DAY
            FROM product
            INNER JOIN product_license ON product_license.id = product.license_id
            WHERE product.active = 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_PRODUCT_REVIEW_PRODUCT');
        $this->addSql('DROP TABLE product_review');
    }
}
