<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616113000 extends AbstractMigration
{
    private const CSV_PATH = '/data/import/product_ultrapop.csv';
    private const TEMP_CATEGORY_NAMES = ['Figurines', 'Mangas', 'Goodies'];
    private const TEMP_LICENSE_NAMES = ['ULTRAPOP'];

    public function getDescription(): string
    {
        return 'Import real ULTRAPOP products from CSV and remove temporary catalog products.';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->removeTemporaryProducts();

        $maxProductId = 0;

        foreach ($this->rows() as $row) {
            $productId = (int) $row['Product ID'];
            $name = trim($row['Nom']);
            $reference = trim($row['Référence']);
            $categoryName = trim($row['Catégorie']);
            $licenseName = trim($row['License']);
            $categoryId = $this->getOrCreateNamedRecord('category', $categoryName, $now);
            $licenseId = $this->getOrCreateNamedRecord('product_license', $licenseName, $now);
            $description = sprintf(
                '%s. Produit ULTRAPOP sous licence %s dans la catégorie %s.',
                $name,
                $licenseName,
                $categoryName,
            );
            $productData = [
                'name' => $name,
                'description' => $description,
                'seo_title' => mb_substr($name, 0, 255),
                'seo_description' => mb_substr($description, 0, 160),
                'reference' => $reference,
                'ean' => null,
                'price_tax_excluded' => $this->decimal($row['Montant HT']),
                'price_tax_included' => $this->decimal($row['Montant TTC']),
                'quantity' => max(0, (int) $row['Quantité']),
                'status' => 'standard',
                'width' => null,
                'height' => null,
                'depth' => null,
                'weight' => null,
                'category_id' => $categoryId,
                'license_id' => $licenseId,
                'updated_at' => $now,
            ];

            $existingId = $this->connection->fetchOne('SELECT id FROM product WHERE reference = ?', [$reference]);

            if (false === $existingId) {
                $productData['id'] = $productId;
                $productData['created_at'] = $now;
                $this->connection->insert('product', $productData);
                $storedProductId = $productId;
            } else {
                $this->connection->update('product', $productData, ['id' => (int) $existingId]);
                $storedProductId = (int) $existingId;
            }

            $this->connection->delete('product_image', ['product_id' => $storedProductId]);
            $this->connection->insert('product_image', [
                'product_id' => $storedProductId,
                'path' => trim($row['Image']),
                'alt' => $name,
                'position' => 0,
                'cover' => 1,
            ]);

            $maxProductId = max($maxProductId, $storedProductId);
        }

        if ($maxProductId > 0) {
            $this->connection->executeStatement(sprintf('ALTER TABLE product AUTO_INCREMENT = %d', $maxProductId + 1));
        }
    }

    public function down(Schema $schema): void
    {
        $references = array_map(
            static fn (array $row): string => trim($row['Référence']),
            $this->rows(),
        );
        $categoryNames = array_values(array_unique(array_map(
            static fn (array $row): string => trim($row['Catégorie']),
            $this->rows(),
        )));
        $licenseNames = array_values(array_unique(array_map(
            static fn (array $row): string => trim($row['License']),
            $this->rows(),
        )));

        foreach ($references as $reference) {
            $productId = $this->connection->fetchOne('SELECT id FROM product WHERE reference = ?', [$reference]);

            if (false === $productId) {
                continue;
            }

            $this->connection->delete('cart_item', ['product_id' => (int) $productId]);
            $this->connection->delete('product_image', ['product_id' => (int) $productId]);
            $this->connection->delete('product', ['id' => (int) $productId]);
        }

        foreach ($categoryNames as $categoryName) {
            $this->deleteNamedRecordIfUnused('category', $categoryName, 'category_id');
        }

        foreach ($licenseNames as $licenseName) {
            $this->deleteNamedRecordIfUnused('product_license', $licenseName, 'license_id');
        }
    }

    private function removeTemporaryProducts(): void
    {
        $this->connection->executeStatement("DELETE FROM cart_item WHERE product_id IN (SELECT id FROM product WHERE reference LIKE 'TEMP-%')");
        $this->connection->executeStatement("DELETE FROM product WHERE reference LIKE 'TEMP-%'");

        foreach (self::TEMP_CATEGORY_NAMES as $categoryName) {
            $this->deleteNamedRecordIfUnused('category', $categoryName, 'category_id');
        }

        foreach (self::TEMP_LICENSE_NAMES as $licenseName) {
            $this->deleteNamedRecordIfUnused('product_license', $licenseName, 'license_id');
        }
    }

    private function getOrCreateNamedRecord(string $table, string $name, string $now): int
    {
        $table = $this->namedTable($table);
        $id = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE name = ?', $table), [$name]);

        if (false !== $id) {
            return (int) $id;
        }

        $this->connection->insert($table, [
            'name' => $name,
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function deleteNamedRecordIfUnused(string $table, string $name, string $productColumn): void
    {
        $table = $this->namedTable($table);
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

    private function namedTable(string $table): string
    {
        return match ($table) {
            'category', 'product_license' => $table,
            default => throw new \InvalidArgumentException(sprintf('Unsupported import table "%s".', $table)),
        };
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(): array
    {
        $path = dirname(__DIR__) . self::CSV_PATH;

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Missing product import file: %s', $path));
        }

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');
        $headers = null;
        $rows = [];

        foreach ($file as $row) {
            if (!is_array($row) || [null] === $row) {
                continue;
            }

            if (null === $headers) {
                $headers = array_map(
                    static fn (?string $header): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)) ?? '',
                    $row,
                );
                continue;
            }

            if (count($row) !== count($headers)) {
                continue;
            }

            /** @var array<string, string> $combined */
            $combined = array_combine($headers, array_map(static fn (?string $value): string => (string) $value, $row));
            $rows[] = $combined;
        }

        return $rows;
    }

    private function decimal(string $value): string
    {
        $normalized = preg_replace('/[^\d,.-]/u', '', $value) ?? '';
        $normalized = str_replace(',', '.', $normalized);

        if ('' === $normalized || '.' === $normalized) {
            $normalized = '0';
        }

        return number_format((float) $normalized, 6, '.', '');
    }
}
