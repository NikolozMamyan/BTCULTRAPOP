<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product VAT rate and infer existing rates from tax excluded and tax included prices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD tax_rate NUMERIC(5, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql(
            "UPDATE product
             SET tax_rate = CASE
                 WHEN price_tax_excluded > 0 AND price_tax_included >= price_tax_excluded
                     THEN LEAST(100.00, ROUND(((price_tax_included / price_tax_excluded) - 1) * 100, 2))
                 ELSE 0.00
             END",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP tax_rate');
    }
}
