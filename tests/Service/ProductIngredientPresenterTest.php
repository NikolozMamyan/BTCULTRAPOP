<?php

namespace App\Tests\Service;

use App\Service\ProductIngredientPresenter;
use PHPUnit\Framework\TestCase;

final class ProductIngredientPresenterTest extends TestCase
{
    public function testItSeparatesIngredientsFromStorageInformation(): void
    {
        $presentation = (new ProductIngredientPresenter())->present(
            'Eau, sucre, extrait de thé noir (0,12 %), acidifiant : acide citrique; '
            . 'correcteur d’acidité : citrates de sodium; arôme, antioxydant : acide ascorbique. '
            . 'Pasteurisée. Conserver dans un endroit frais et sec. '
            . 'À consommer de préférence avant : voir le fond de la canette.',
        );

        self::assertSame([
            'Eau',
            'sucre',
            'extrait de thé noir (0,12 %)',
            'acidifiant : acide citrique',
            'correcteur d’acidité : citrates de sodium',
            'arôme',
            'antioxydant : acide ascorbique',
        ], array_column($presentation['items'], 'name'));
        self::assertSame('fa-droplet', $presentation['items'][0]['icon']);
        self::assertSame('fa-cubes-stacked', $presentation['items'][1]['icon']);
        self::assertSame('fa-leaf', $presentation['items'][2]['icon']);
        self::assertSame(
            'Pasteurisée. Conserver dans un endroit frais et sec. À consommer de préférence avant : voir le fond de la canette.',
            $presentation['information'],
        );
    }

    public function testItKeepsNestedIngredientGroupsTogether(): void
    {
        $presentation = (new ProductIngredientPresenter())->present(
            'Infusion 89% (eau, thé noir, baies d’églantiers et fleurs d’hibiscus), '
            . 'jus de pêche à base de concentré, sucre. Après ouverture, conserver au réfrigérateur.',
        );

        self::assertSame([
            'Infusion 89% (eau, thé noir, baies d’églantiers et fleurs d’hibiscus)',
            'jus de pêche à base de concentré',
            'sucre',
        ], array_column($presentation['items'], 'name'));
        self::assertSame(
            'Après ouverture, conserver au réfrigérateur.',
            $presentation['information'],
        );
    }

    public function testItSupportsACompositionWithoutUsageInformation(): void
    {
        $presentation = (new ProductIngredientPresenter())->present(
            'Riz brun complet 77 %, huile d’olive, assaisonnement barbecue 7 % (sucre, sel, paprika).',
        );

        self::assertCount(3, $presentation['items']);
        self::assertSame('fa-wheat-awn', $presentation['items'][0]['icon']);
        self::assertSame('fa-bottle-droplet', $presentation['items'][1]['icon']);
        self::assertNull($presentation['information']);
    }

    public function testAllImportedCompositionsKeepUsageInstructionsOutOfTheIngredientList(): void
    {
        /** @var list<array{references: list<string>, ingredients: string}> $groups */
        $groups = require dirname(__DIR__, 2) . '/migrations/data/product_ingredients.php';
        $presenter = new ProductIngredientPresenter();

        foreach ($groups as $group) {
            $presentation = $presenter->present($group['ingredients']);

            self::assertNotEmpty($presentation['items']);

            foreach ($presentation['items'] as $item) {
                self::assertDoesNotMatchRegularExpression(
                    '/^(?:pasteuris|conserver|à conserver|a conserver|consommer|à consommer|a consommer|'
                    . 'après ouverture|une fois ouvert|bien agiter|conditionné|ne convient|'
                    . 'le produit ne convient|servir|ce paquet|peut contenir|les extraits)/iu',
                    $item['name'],
                    sprintf('Instruction incorrectly displayed as an ingredient: %s', $item['name']),
                );
            }
        }
    }
}
