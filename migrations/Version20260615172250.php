<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615172250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create database users and their named shipping addresses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, street VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, country_code VARCHAR(2) DEFAULT \'FR\' NOT NULL, phone VARCHAR(30) DEFAULT NULL, default_address TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_D4E6F81A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, phone VARCHAR(30) DEFAULT NULL, preferred_locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, verified TINYINT DEFAULT 0 NOT NULL, active TINYINT DEFAULT 1 NOT NULL, last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81A76ED395');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE app_user');
    }
}
