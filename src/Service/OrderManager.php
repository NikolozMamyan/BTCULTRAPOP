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
use App\Enum\StockSource;
use App\Model\CheckoutAddress;

final class OrderManager
{
    public function __construct(
        private readonly ?OrderNumberGenerator $orderNumberGenerator = null,
        private readonly ?PromoCodeManager $promoCodeManager = null,
        private readonly ?StockSettingsManager $stockSettingsManager = null,
        private readonly ?ProductStockSourceWriter $stockSourceWriter = null,
    ) {
    }

    public function createFromCart(
        Cart $cart,
        User $user,
        Address $shippingAddress,
        int $shippingAmountTaxExcludedCents = 0,
        int $shippingAmountTaxIncludedCents = 0,
        int $discountAmountTaxExcludedCents = 0,
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
            ->setCart($cart)
            ->setCustomerEmail($user->getEmail())
            ->setCustomerName($user->getFullName() ?: $user->getEmail())
            ->setShippingName($shippingAddress->getName())
            ->setShippingStreet($shippingAddress->getStreet())
            ->setShippingPostalCode($shippingAddress->getPostalCode())
            ->setShippingCity($shippingAddress->getCity())
            ->setShippingCountryCode($shippingAddress->getCountryCode())
            ->setShippingPhone($shippingAddress->getPhone())
            ->setShippingAmountTaxExcludedCents($shippingAmountTaxExcludedCents)
            ->setShippingAmountTaxIncludedCents($shippingAmountTaxIncludedCents)
            ->setDiscountAmountTaxExcludedCents($discountAmountTaxExcludedCents)
            ->setDiscountAmountTaxIncludedCents($discountAmountTaxIncludedCents)
            ->setPromoCode($discountAmountTaxIncludedCents > 0 ? $cart->getPromoCode() : null);

        foreach ($cart->getItems() as $cartItem) {
            $order->addItem($this->createOrderItem($cartItem));
        }

        $order->refreshTotals();
        $this->reservePromotion($order);
        $cart->markConverted();

        return $order;
    }

    public function createGuestFromCart(
        Cart $cart,
        CheckoutAddress $shippingAddress,
        ?User $user = null,
        int $shippingAmountTaxExcludedCents = 0,
        int $shippingAmountTaxIncludedCents = 0,
        int $discountAmountTaxExcludedCents = 0,
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
            ->setCart($cart)
            ->setCustomerEmail($customerEmail ?? $user?->getEmail())
            ->setCustomerName($shippingAddress->name)
            ->setShippingName($shippingAddress->name)
            ->setShippingStreet($shippingAddress->street)
            ->setShippingPostalCode($shippingAddress->postalCode)
            ->setShippingCity($shippingAddress->city)
            ->setShippingCountryCode($shippingAddress->countryCode)
            ->setShippingPhone($shippingAddress->phone)
            ->setShippingAmountTaxExcludedCents($shippingAmountTaxExcludedCents)
            ->setShippingAmountTaxIncludedCents($shippingAmountTaxIncludedCents)
            ->setDiscountAmountTaxExcludedCents($discountAmountTaxExcludedCents)
            ->setDiscountAmountTaxIncludedCents($discountAmountTaxIncludedCents)
            ->setPromoCode($discountAmountTaxIncludedCents > 0 ? $cart->getPromoCode() : null);

        foreach ($cart->getItems() as $cartItem) {
            $order->addItem($this->createOrderItem($cartItem));
        }

        $order->refreshTotals();
        $this->reservePromotion($order);
        $cart->markConverted();

        return $order;
    }

    public function markPaid(Order $order, ?\DateTimeImmutable $paidAt = null): void
    {
        if (PaymentStatus::PAID === $order->getPaymentStatus()) {
            return;
        }

        $this->promoCodeManager?->redeemForOrder($order);
        $order->markPaid($paidAt);
        $order->getCart()?->markConverted();
        $order->getUser()?->addLoyaltyPoints($order->getLoyaltyPointsEarned());
        $activeStockSource = $this->stockSettingsManager?->activeSource() ?? StockSource::default();
        $updatedProducts = [];

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if ($product instanceof Product) {
                $product->setQuantity(max(0, $product->getQuantity() - $item->getQuantity()));
                $updatedProducts[] = $product;
            }
        }

        foreach ($updatedProducts as $product) {
            $this->stockSourceWriter?->write($product, $activeStockSource, $product->getQuantity());
        }
    }

    public function markPaymentFailed(Order $order, ?string $reason = null): void
    {
        $this->promoCodeManager?->releaseForOrder($order);
        $order->markPaymentFailed($reason);
    }

    public function cancel(
        Order $order,
        ?\DateTimeImmutable $cancelledAt = null,
        ?string $reason = null,
    ): void
    {
        $this->promoCodeManager?->releaseForOrder($order);
        $order->cancel($cancelledAt, $reason);
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
            ->setTaxRate($product->getTaxRate());
    }

    private function reservePromotion(Order $order): void
    {
        if (null === $order->getPromoCode()) {
            return;
        }

        if (!$this->promoCodeManager instanceof PromoCodeManager) {
            throw new \LogicException('PromoCodeManager is required to reserve a promotional code.');
        }

        $this->promoCodeManager->reserveForOrder($order);
    }

    private function generateOrderNumber(): string
    {
        if (!$this->orderNumberGenerator instanceof OrderNumberGenerator) {
            throw new \LogicException('OrderNumberGenerator is required to create an automatic order number.');
        }

        return $this->orderNumberGenerator->generate();
    }
}
