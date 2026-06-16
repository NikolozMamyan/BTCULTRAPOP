<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create daily order sequence table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_sequence (date_key VARCHAR(8) NOT NULL, next_number INT NOT NULL, PRIMARY KEY (date_key)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE order_sequence');
    }
}
