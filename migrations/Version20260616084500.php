<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616084500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional EAN code to products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD ean VARCHAR(13) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP ean');
    }
}
