<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user favorite products table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_favorite (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_88486AD9A76ED395 (user_id), INDEX IDX_88486AD94584665A (product_id), UNIQUE INDEX uniq_user_favorite_user_product (user_id, product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_favorite ADD CONSTRAINT FK_88486AD9A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_favorite ADD CONSTRAINT FK_88486AD94584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_favorite DROP FOREIGN KEY FK_88486AD9A76ED395');
        $this->addSql('ALTER TABLE user_favorite DROP FOREIGN KEY FK_88486AD94584665A');
        $this->addSql('DROP TABLE user_favorite');
    }
}
