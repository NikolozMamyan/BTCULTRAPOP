<?php

namespace App\Service;

final class ProductIngredientPresenter
{
    private const INFORMATION_MARKERS = [
        'pasteuris(?:é|ée|és|ées)',
        '(?:à|a)\s+conserver',
        'conserver',
        'consommer',
        '(?:à|a)\s+consommer',
        'après\s+ouverture',
        'une\s+fois\s+ouvert',
        'bien\s+agiter',
        'conditionné',
        'ne\s+convient',
        'le\s+produit\s+ne\s+convient',
        'servir',
        'ce\s+paquet',
        'peut\s+contenir',
        'les\s+extraits',
    ];

    /**
     * @return array{
     *     items: list<array{name: string, icon: string, tone: string}>,
     *     information: string|null
     * }
     */
    public function present(?string $rawIngredients): array
    {
        $rawIngredients = trim((string) $rawIngredients);

        if ('' === $rawIngredients) {
            return [
                'items' => [],
                'information' => null,
            ];
        }

        [$composition, $information] = $this->splitCompositionAndInformation($rawIngredients);

        return [
            'items' => array_map(
                fn (string $ingredient): array => [
                    'name' => $ingredient,
                    ...$this->visualFor($ingredient),
                ],
                $this->splitIngredients($composition),
            ),
            'information' => $information,
        ];
    }

    /**
     * @return array{string, string|null}
     */
    private function splitCompositionAndInformation(string $ingredients): array
    {
        $ingredients = preg_replace('/[ \t]+/u', ' ', str_replace("\r\n", "\n", $ingredients)) ?? $ingredients;
        $markerPattern = implode('|', self::INFORMATION_MARKERS);
        $pattern = sprintf('/(?:^|(?<=[.!?])\s+|\R+)(?=(?:%s)\b)/iu', $markerPattern);

        if (1 !== preg_match($pattern, $ingredients, $matches, \PREG_OFFSET_CAPTURE)) {
            return [$this->cleanText($ingredients), null];
        }

        $offset = $matches[0][1];
        $composition = $this->cleanText(substr($ingredients, 0, $offset));
        $information = $this->cleanText(substr($ingredients, $offset));

        return [$composition, '' === $information ? null : $information];
    }

    /**
     * @return list<string>
     */
    private function splitIngredients(string $composition): array
    {
        $items = [];
        $buffer = '';
        $roundDepth = 0;
        $squareDepth = 0;
        $characters = preg_split('//u', str_replace("\n", ' ', $composition), -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $characters) {
            return [$composition];
        }

        foreach ($characters as $character) {
            if ('(' === $character) {
                ++$roundDepth;
            } elseif (')' === $character) {
                $roundDepth = max(0, $roundDepth - 1);
            } elseif ('[' === $character) {
                ++$squareDepth;
            } elseif (']' === $character) {
                $squareDepth = max(0, $squareDepth - 1);
            }

            if ((',' === $character || ';' === $character) && 0 === $roundDepth && 0 === $squareDepth) {
                $this->appendItem($items, $buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        $this->appendItem($items, $buffer);

        return $items;
    }

    /**
     * @param list<string> $items
     */
    private function appendItem(array &$items, string $item): void
    {
        $item = trim($item);
        $item = rtrim($item, " \t\n\r\0\x0B.");

        if ('' !== $item) {
            $items[] = $item;
        }
    }

    /**
     * @return array{icon: string, tone: string}
     */
    private function visualFor(string $ingredient): array
    {
        $ingredient = mb_strtolower($ingredient);

        return match (true) {
            str_contains($ingredient, 'eau') => ['icon' => 'fa-droplet', 'tone' => 'blue'],
            $this->containsAny($ingredient, ['sucre', 'sirop', 'fructose', 'dextrose', 'miel']) => ['icon' => 'fa-cubes-stacked', 'tone' => 'amber'],
            $this->containsAny($ingredient, ['thé', 'infusion', 'hibiscus', 'herbe']) => ['icon' => 'fa-leaf', 'tone' => 'green'],
            $this->containsAny($ingredient, ['jus', 'fruit', 'purée', 'arôme', 'citron', 'orange', 'pêche', 'fraise', 'cerise', 'litchi', 'mangue']) => ['icon' => 'fa-lemon', 'tone' => 'orange'],
            $this->containsAny($ingredient, ['farine', 'blé', 'riz', 'avoine', 'orge', 'maïs', 'nouille', 'pomme de terre', 'amidon', 'tapioca']) => ['icon' => 'fa-wheat-awn', 'tone' => 'gold'],
            $this->containsAny($ingredient, ['huile', 'graisse']) => ['icon' => 'fa-bottle-droplet', 'tone' => 'olive'],
            $this->containsAny($ingredient, ['sel', 'épice', 'assaisonnement', 'poivre', 'paprika', 'gingembre', 'ail', 'oignon']) => ['icon' => 'fa-mortar-pestle', 'tone' => 'violet'],
            $this->containsAny($ingredient, ['lait', 'crème', 'lactosérum']) => ['icon' => 'fa-cow', 'tone' => 'sky'],
            $this->containsAny($ingredient, ['gélatine']) => ['icon' => 'fa-bone', 'tone' => 'rose'],
            $this->containsAny($ingredient, ['acidifiant', 'acidité', 'antioxydant', 'conservateur', 'colorant', 'stabilisant', 'émulsifiant', 'épaississant', 'régulateur', 'correcteur', 'exhausteur', 'agent levant']) => ['icon' => 'fa-flask', 'tone' => 'purple'],
            default => ['icon' => 'fa-seedling', 'tone' => 'green'],
        };
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
