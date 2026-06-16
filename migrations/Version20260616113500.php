<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616113500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove orphaned placeholder catalog categories and licenses.';
    }

    public function up(Schema $schema): void
    {
        foreach (['Figurines', 'Mangas', 'Goodies'] as $categoryName) {
            $this->deleteNamedRecordIfUnused('category', $categoryName, 'category_id');
        }

        foreach (['ULTRAPOP'] as $licenseName) {
            $this->deleteNamedRecordIfUnused('product_license', $licenseName, 'license_id');
        }
    }

    public function down(Schema $schema): void
    {
        // The removed records were placeholders without products. They should not be recreated.
    }

    private function deleteNamedRecordIfUnused(string $table, string $name, string $productColumn): void
    {
        $table = match ($table) {
            'category', 'product_license' => $table,
            default => throw new \InvalidArgumentException(sprintf('Unsupported table "%s".', $table)),
        };
        $id = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE name = ?', $table), [$name]);

        if (false === $id) {
            return;
        }

        $products = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM product WHERE %s = ?', $productColumn),
            [(int) $id],
        );

        if (0 === $products) {
            $this->connection->delete($table, ['id' => (int) $id]);
        }
    }
}
