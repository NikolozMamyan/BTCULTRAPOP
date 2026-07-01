<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move legacy category 3D model presets to product-level 3D model presets when needed.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('product_model_3d')) {
            $this->addSql(
                'CREATE TABLE product_model_3d (
                    id INT AUTO_INCREMENT NOT NULL,
                    product_id INT NOT NULL,
                    active TINYINT(1) DEFAULT 1 NOT NULL,
                    model_type VARCHAR(40) DEFAULT \'can\' NOT NULL,
                    texture_path VARCHAR(255) DEFAULT NULL,
                    width_scale DOUBLE PRECISION NOT NULL,
                    height DOUBLE PRECISION NOT NULL,
                    body_bulge DOUBLE PRECISION NOT NULL,
                    shoulder DOUBLE PRECISION NOT NULL,
                    top_cut DOUBLE PRECISION NOT NULL,
                    top_neck DOUBLE PRECISION NOT NULL,
                    bottom_neck DOUBLE PRECISION NOT NULL,
                    lid_scale DOUBLE PRECISION NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_F29168AE4584665A (product_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );
            $this->addSql('ALTER TABLE product_model_3d ADD CONSTRAINT FK_PRODUCT_MODEL_3D_PRODUCT_ID FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }

        if ($schema->hasTable('category_model_3d')) {
            $this->addSql(
                'INSERT IGNORE INTO product_model_3d (
                    product_id,
                    active,
                    model_type,
                    texture_path,
                    width_scale,
                    height,
                    body_bulge,
                    shoulder,
                    top_cut,
                    top_neck,
                    bottom_neck,
                    lid_scale,
                    created_at,
                    updated_at
                )
                SELECT
                    product.id,
                    category_model.active,
                    CASE
                        WHEN LOWER(category.name) = \'jus\' THEN \'bottle\'
                        WHEN LOWER(category.name) IN (\'chipsan - chips de pommes de terre\', \'komesan - chips de riz\') THEN \'chip_bag\'
                        WHEN LOWER(category.name) LIKE \'%nouilles%\' OR LOWER(category.name) LIKE \'%negisan%\' OR LOWER(category.name) LIKE \'%négisan%\' THEN \'noodle_cup\'
                        WHEN LOWER(category.name) LIKE \'%yokosan%\' OR LOWER(category.name) LIKE \'%céréales%\' OR LOWER(category.name) LIKE \'%cereales%\' THEN \'cereal_box\'
                        WHEN LOWER(category.name) LIKE \'%gumisan%\' AND (LOWER(product.name) LIKE \'%fils%\' OR LOWER(product.name) LIKE \'%rubans%\' OR LOWER(product.name) LIKE \'%75g%\' OR LOWER(product.name) LIKE \'%75gr%\') THEN \'candy_stick_bag\'
                        WHEN LOWER(category.name) LIKE \'%gumisan%\' THEN \'candy_bag\'
                        ELSE \'can\'
                    END,
                    category_model.texture_path,
                    category_model.width_scale,
                    category_model.height,
                    category_model.body_bulge,
                    category_model.shoulder,
                    category_model.top_cut,
                    category_model.top_neck,
                    category_model.bottom_neck,
                    category_model.lid_scale,
                    category_model.created_at,
                    category_model.updated_at
                FROM category_model_3d category_model
                INNER JOIN product product ON product.category_id = category_model.category_id
                INNER JOIN category category ON category.id = product.category_id',
            );
            $this->addSql('DROP TABLE category_model_3d');
        }
    }

    public function down(Schema $schema): void
    {
        // This migration is a compatibility bridge for environments that already ran
        // the previous category-level draft migration. The product table is owned by
        // Version20260626100000, so rolling this bridge back must not drop it.
    }
}
