<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;

final class CartManager
{
    public function createCart(?User $user = null, ?string $token = null): Cart
    {
        return (new Cart())
            ->setUser($user)
            ->setToken($token ?? $this->generateToken());
    }

    public function addProduct(Cart $cart, Product $product, int $quantity = 1): CartItem
    {
        if (!$cart->isActive()) {
            throw new \InvalidArgumentException('cart.error.not_active');
        }

        $quantity = max(1, $quantity);
        $item = $cart->getItemForProduct($product);

        if ($item instanceof CartItem) {
            return $item->incrementQuantity($quantity);
        }

        $item = (new CartItem())
            ->setProduct($product)
            ->setQuantity($quantity)
            ->setUnitPriceTaxExcludedCents($this->decimalToCents($product->getPriceTaxExcluded()))
            ->setUnitPriceTaxIncludedCents($this->decimalToCents($product->getPriceTaxIncluded()));

        $cart->addItem($item);

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $item->setQuantity($quantity);
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        $cart->removeItem($item);
    }

    public function merge(Cart $source, Cart $target): void
    {
        if ($source === $target) {
            return;
        }

        if (!$source->isActive() || !$target->isActive()) {
            throw new \InvalidArgumentException('cart.error.not_active');
        }

        foreach ($source->getItems()->toArray() as $sourceItem) {
            $product = $sourceItem->getProduct();

            if (null === $product) {
                $source->removeItem($sourceItem);
                continue;
            }

            $targetItem = $target->getItemForProduct($product);

            if ($targetItem instanceof CartItem) {
                $targetItem->incrementQuantity($sourceItem->getQuantity());
                $source->removeItem($sourceItem);
                continue;
            }

            $target->addItem((new CartItem())
                ->setProduct($product)
                ->setQuantity($sourceItem->getQuantity())
                ->setUnitPriceTaxExcludedCents($sourceItem->getUnitPriceTaxExcludedCents())
                ->setUnitPriceTaxIncludedCents($sourceItem->getUnitPriceTaxIncludedCents()));
            $source->removeItem($sourceItem);
        }

        $source->abandon();
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function decimalToCents(string $amount): int
    {
        $normalized = trim(str_replace(',', '.', $amount));

        if (!preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException('cart.error.invalid_price');
        }

        [$units, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');
        $cents = ((int) $units * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            ++$cents;
        }

        return $cents;
    }
}
