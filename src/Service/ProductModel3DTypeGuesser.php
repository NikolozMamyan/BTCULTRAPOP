<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductModel3D;

final class ProductModel3DTypeGuesser
{
    public function guess(Product $product): string
    {
        $categoryText = $this->normalize(implode(' ', $product->getCategory()?->getPathNames() ?? []));
        $productText = $this->normalize($product->getName() . ' ' . $product->getReference());
        $text = trim($categoryText . ' ' . $productText);

        if ($this->contains($text, ['jus'])) {
            return ProductModel3D::TYPE_BOTTLE;
        }

        if ($this->contains($text, ['bobbasan', 'bubble tea', 'sodas', 'soda', 'ultra ice tea', 'ice tea'])) {
            return ProductModel3D::TYPE_CAN;
        }

        if ($this->contains($text, ['chipsan', 'komesan', 'chips de riz', 'chips de pommes de terre'])) {
            return ProductModel3D::TYPE_CHIP_BAG;
        }

        if ($this->contains($text, ['negisan', 'nouilles instantanees', 'cup noodles', 'cup nouilles'])) {
            return ProductModel3D::TYPE_NOODLE_CUP;
        }

        if ($this->contains($text, ['yokosan', 'cereales', 'boites de cereales', 'boite de cereales'])) {
            return ProductModel3D::TYPE_CEREAL_BOX;
        }

        if ($this->contains($text, ['gumisan', 'bonbons', 'bonbon'])) {
            if ($this->contains($text, ['fils', 'rubans', '75g', '75gr'])) {
                return ProductModel3D::TYPE_CANDY_STICK_BAG;
            }

            return ProductModel3D::TYPE_CANDY_BAG;
        }

        return ProductModel3D::DEFAULT_MODEL_TYPE;
    }

    /**
     * @param list<Product> $products
     *
     * @return array<int, string>
     */
    public function guessIndexed(array $products): array
    {
        $indexed = [];

        foreach ($products as $product) {
            $productId = $product->getId();

            if (null !== $productId) {
                $indexed[$productId] = $this->guess($product);
            }
        }

        return $indexed;
    }

    /**
     * @param list<string> $needles
     */
    private function contains(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ÿ' => 'y',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
