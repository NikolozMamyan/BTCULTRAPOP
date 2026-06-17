<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Model\CheckoutAddress;

final class OrderManager
{
    public function __construct(private readonly ?OrderNumberGenerator $orderNumberGenerator = null)
    {
    }

    public function createFromCart(
        Cart $cart,
        User $user,
        Address $shippingAddress,
        int $shippingAmountTaxIncludedCents = 0,
        int $discountAmountTaxIncludedCents = 0,
        ?string $orderNumber = null,
    ): Order {
        if (!$cart->isActive()) {
            throw new \InvalidArgumentException('order.error.cart_not_active');
        }

        if (0 === $cart->getItems()->count()) {
            throw new \InvalidArgumentException('order.error.empty_cart');
        }

        $order = (new Order())
            ->setOrderNumber($orderNumber ?? $this->generateOrderNumber())
            ->setUser($user)
            ->setCustomerEmail($user->getEmail())
            ->setCustomerName($user->getFullName() ?: $user->getEmail())
            ->setShippingName($shippingAddress->getName())
            ->setShippingStreet($shippingAddress->getStreet())
            ->setShippingPostalCode($shippingAddress->getPostalCode())
            ->setShippingCity($shippingAddress->getCity())
            ->setShippingCountryCode($shippingAddress->getCountryCode())
            ->setShippingPhone($shippingAddress->getPhone())
            ->setShippingAmountTaxIncludedCents($shippingAmountTaxIncludedCents)
            ->setDiscountAmountTaxIncludedCents($discountAmountTaxIncludedCents);

        foreach ($cart->getItems() as $cartItem) {
            $order->addItem($this->createOrderItem($cartItem));
        }

        $order->refreshTotals();
        $cart->markConverted();

        return $order;
    }

    public function createGuestFromCart(
        Cart $cart,
        CheckoutAddress $shippingAddress,
        ?User $user = null,
        int $shippingAmountTaxIncludedCents = 0,
        int $discountAmountTaxIncludedCents = 0,
        ?string $customerEmail = null,
        ?string $orderNumber = null,
    ): Order {
        if (!$cart->isActive()) {
            throw new \InvalidArgumentException('order.error.cart_not_active');
        }

        if (0 === $cart->getItems()->count()) {
            throw new \InvalidArgumentException('order.error.empty_cart');
        }

        $order = (new Order())
            ->setOrderNumber($orderNumber ?? $this->generateOrderNumber())
            ->setUser($user)
            ->setCustomerEmail($customerEmail ?? $user?->getEmail())
            ->setCustomerName($shippingAddress->name)
            ->setShippingName($shippingAddress->name)
            ->setShippingStreet($shippingAddress->street)
            ->setShippingPostalCode($shippingAddress->postalCode)
            ->setShippingCity($shippingAddress->city)
            ->setShippingCountryCode($shippingAddress->countryCode)
            ->setShippingPhone($shippingAddress->phone)
            ->setShippingAmountTaxIncludedCents($shippingAmountTaxIncludedCents)
            ->setDiscountAmountTaxIncludedCents($discountAmountTaxIncludedCents);

        foreach ($cart->getItems() as $cartItem) {
            $order->addItem($this->createOrderItem($cartItem));
        }

        $order->refreshTotals();
        $cart->markConverted();

        return $order;
    }

    public function markPaid(Order $order, ?\DateTimeImmutable $paidAt = null): void
    {
        if (PaymentStatus::PAID === $order->getPaymentStatus()) {
            return;
        }

        $order->markPaid($paidAt);
        $order->getUser()?->addLoyaltyPoints($order->getLoyaltyPointsEarned());

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if ($product instanceof Product) {
                $product->setQuantity(max(0, $product->getQuantity() - $item->getQuantity()));
            }
        }
    }

    private function createOrderItem(CartItem $cartItem): OrderItem
    {
        $product = $cartItem->getProduct();

        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('order.error.product_missing');
        }

        return (new OrderItem())
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setProductReference($product->getReference())
            ->setProductEan($product->getEan())
            ->setProductImage($product->getCoverImage()?->getPath())
            ->setCategoryName($product->getCategory()?->getName())
            ->setLicenseName($product->getLicense()?->getName())
            ->setQuantity($cartItem->getQuantity())
            ->setUnitPriceTaxExcludedCents($cartItem->getUnitPriceTaxExcludedCents())
            ->setUnitPriceTaxIncludedCents($cartItem->getUnitPriceTaxIncludedCents())
            ->setTaxRate($this->calculateTaxRate(
                $cartItem->getUnitPriceTaxExcludedCents(),
                $cartItem->getUnitPriceTaxIncludedCents(),
            ));
    }

    private function calculateTaxRate(int $taxExcludedCents, int $taxIncludedCents): string
    {
        if ($taxExcludedCents <= 0 || $taxIncludedCents <= $taxExcludedCents) {
            return '0.00';
        }

        return number_format((($taxIncludedCents - $taxExcludedCents) / $taxExcludedCents) * 100, 2, '.', '');
    }

    private function generateOrderNumber(): string
    {
        if (!$this->orderNumberGenerator instanceof OrderNumberGenerator) {
            throw new \LogicException('OrderNumberGenerator is required to create an automatic order number.');
        }

        return $this->orderNumberGenerator->generate();
    }
}
