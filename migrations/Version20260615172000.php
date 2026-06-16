<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the physical product condition with a commercial product status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE product SET `condition` = 'standard'");
        $this->addSql("ALTER TABLE product CHANGE `condition` status VARCHAR(20) NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE product SET status = 'new'");
        $this->addSql("ALTER TABLE product CHANGE status `condition` VARCHAR(20) NOT NULL");
    }
}
