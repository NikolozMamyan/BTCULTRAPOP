<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional tax-included sale price to products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD promo_price_tax_included NUMERIC(20, 6) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP promo_price_tax_included');
    }
}
