<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623140000 extends AbstractMigration
{
    private const EXPECTED_DESCRIPTION_COUNT = 84;

    public function getDescription(): string
    {
        return 'Add concise manga-inspired storefront descriptions to the 84 supplied product references.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->descriptions() as $reference => $description) {
            $this->addSql(
                'UPDATE product SET description = :description WHERE reference = :reference',
                [
                    'description' => $description,
                    'reference' => $reference,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Previous product descriptions cannot be reconstructed reliably across environments.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function descriptions(): array
    {
        $path = __DIR__ . '/data/product_descriptions.php';
        $descriptions = require $path;

        if (!is_array($descriptions) || self::EXPECTED_DESCRIPTION_COUNT !== count($descriptions)) {
            throw new \RuntimeException(sprintf(
                'Expected %d product descriptions in %s.',
                self::EXPECTED_DESCRIPTION_COUNT,
                $path,
            ));
        }

        $normalizedDescriptions = [];

        foreach ($descriptions as $reference => $description) {
            $reference = (string) $reference;

            if ('' === $reference || !is_string($description) || '' === trim($description)) {
                throw new \RuntimeException(sprintf('Invalid product description for reference "%s".', (string) $reference));
            }

            $normalizedDescriptions[$reference] = trim($description);
        }

        return $normalizedDescriptions;
    }
}
