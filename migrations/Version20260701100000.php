<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product 3D model type to support bottles, bags, cups and boxes.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('product_model_3d') && !$schema->getTable('product_model_3d')->hasColumn('model_type')) {
            $this->addSql("ALTER TABLE product_model_3d ADD model_type VARCHAR(40) DEFAULT 'can' NOT NULL AFTER active");
        }

        $this->addSql(
            "UPDATE product_model_3d model
            INNER JOIN product product ON product.id = model.product_id
            INNER JOIN category category ON category.id = product.category_id
            SET model.model_type = CASE
                WHEN LOWER(category.name) = 'jus' THEN 'bottle'
                WHEN LOWER(category.name) IN ('chipsan - chips de pommes de terre', 'komesan - chips de riz') THEN 'chip_bag'
                WHEN LOWER(category.name) LIKE '%nouilles%' OR LOWER(category.name) LIKE '%negisan%' OR LOWER(category.name) LIKE '%négisan%' THEN 'noodle_cup'
                WHEN LOWER(category.name) LIKE '%yokosan%' OR LOWER(category.name) LIKE '%céréales%' OR LOWER(category.name) LIKE '%cereales%' THEN 'cereal_box'
                WHEN LOWER(category.name) LIKE '%gumisan%' AND (LOWER(product.name) LIKE '%fils%' OR LOWER(product.name) LIKE '%rubans%' OR LOWER(product.name) LIKE '%75g%' OR LOWER(product.name) LIKE '%75gr%') THEN 'candy_stick_bag'
                WHEN LOWER(category.name) LIKE '%gumisan%' THEN 'candy_bag'
                ELSE 'can'
            END",
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('product_model_3d') && $schema->getTable('product_model_3d')->hasColumn('model_type')) {
            $this->addSql('ALTER TABLE product_model_3d DROP model_type');
        }
    }
}
