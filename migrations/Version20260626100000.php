<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product-based 3D model presets for storefront previews.';
    }

    public function up(Schema $schema): void
    {
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

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_model_3d DROP FOREIGN KEY FK_PRODUCT_MODEL_3D_PRODUCT_ID');
        $this->addSql('DROP TABLE product_model_3d');
    }
}
