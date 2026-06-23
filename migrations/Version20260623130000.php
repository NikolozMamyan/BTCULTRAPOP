<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623130000 extends AbstractMigration
{
    private const EXPECTED_REFERENCE_COUNT = 75;

    public function getDescription(): string
    {
        return 'Add product ingredients and import 75 verified compositions from the legacy ULTRAPOP catalog.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD ingredients LONGTEXT DEFAULT NULL');

        foreach ($this->ingredientGroups() as $group) {
            foreach ($group['references'] as $reference) {
                $this->addSql(
                    'UPDATE product SET ingredients = :ingredients WHERE reference = :reference',
                    [
                        'ingredients' => $group['ingredients'],
                        'reference' => $reference,
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP ingredients');
    }

    /**
     * @return list<array{references: list<string>, ingredients: string}>
     */
    private function ingredientGroups(): array
    {
        $path = __DIR__ . '/data/product_ingredients.php';
        $groups = require $path;

        if (!is_array($groups)) {
            throw new \RuntimeException(sprintf('Invalid ingredient import file: %s', $path));
        }

        $referenceCount = 0;
        $seenReferences = [];

        foreach ($groups as $group) {
            if (
                !is_array($group)
                || !isset($group['references'], $group['ingredients'])
                || !is_array($group['references'])
                || !is_string($group['ingredients'])
                || '' === trim($group['ingredients'])
            ) {
                throw new \RuntimeException(sprintf('Invalid ingredient group in %s', $path));
            }

            foreach ($group['references'] as $reference) {
                if (!is_string($reference) || '' === $reference || isset($seenReferences[$reference])) {
                    throw new \RuntimeException(sprintf('Invalid or duplicate product reference "%s" in %s', (string) $reference, $path));
                }

                $seenReferences[$reference] = true;
                ++$referenceCount;
            }
        }

        if (self::EXPECTED_REFERENCE_COUNT !== $referenceCount) {
            throw new \RuntimeException(sprintf(
                'Expected %d ingredient references in %s, got %d.',
                self::EXPECTED_REFERENCE_COUNT,
                $path,
                $referenceCount,
            ));
        }

        return $groups;
    }
}
