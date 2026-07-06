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
            $this->applyProductPrice($item, $product);

            return $item->incrementQuantity($quantity);
        }

        $item = (new CartItem())
            ->setProduct($product)
            ->setQuantity($quantity);

        $this->applyProductPrice($item, $product);

        $cart->addItem($item);

        return $item;
    }

    public function refreshPrices(Cart $cart): void
    {
        if (!$cart->isActive()) {
            return;
        }

        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();

            if ($product instanceof Product) {
                $this->applyProductPrice($item, $product);
            }
        }
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $item->setQuantity($quantity);
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        $cart->removeItem($item);

        if (0 === $cart->getItems()->count()) {
            $cart->setPromoCode(null);
        }
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

        if (null === $target->getPromoCode() && null !== $source->getPromoCode()) {
            $target->setPromoCode($source->getPromoCode());
        }

        $source->setPromoCode(null);
        $source->abandon();
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function applyProductPrice(CartItem $item, Product $product): void
    {
        $item
            ->setUnitPriceTaxExcludedCents($this->decimalToCents($product->getEffectivePriceTaxExcluded()))
            ->setUnitPriceTaxIncludedCents($this->decimalToCents($product->getEffectivePriceTaxIncluded()));
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
