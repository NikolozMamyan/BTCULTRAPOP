<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add icon path fields for categories and product licenses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD icon_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_license ADD icon_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP icon_path');
        $this->addSql('ALTER TABLE product_license DROP icon_path');
    }
}
