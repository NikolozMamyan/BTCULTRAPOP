<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Service\OrderPaymentLinkSigner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OrderPaymentLinkSignerTest extends TestCase
{
    public function testRecoveryUrlIsSignedAndBoundToTheOrder(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_checkout_payment_recovery', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://www.ultrapop.com/checkout/recovery/42');
        $signer = new OrderPaymentLinkSigner($urlGenerator, 'test-secret');
        $url = $signer->recoveryUrl($this->orderWithId(42));

        self::assertStringContainsString('_signature=', $url);
        self::assertStringContainsString('_expires=', $url);
        self::assertTrue($signer->isValid(Request::create($url)));
        self::assertFalse($signer->isValid(Request::create(str_replace('/42?', '/43?', $url))));
    }

    public function testCheckoutCancelUrlUsesTheSignedOrderId(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_checkout_cancel', ['id' => 7], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://www.ultrapop.com/checkout/cancel/7');
        $signer = new OrderPaymentLinkSigner($urlGenerator, 'test-secret');
        $url = $signer->checkoutCancelUrl($this->orderWithId(7));

        self::assertTrue($signer->isValid(Request::create($url)));
    }

    public function testCartRecoveryUrlUsesTheSignedOrderId(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_checkout_cart_recovery', ['id' => 9], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://www.ultrapop.com/checkout/cart/9');
        $signer = new OrderPaymentLinkSigner($urlGenerator, 'test-secret');
        $url = $signer->cartRecoveryUrl($this->orderWithId(9));

        self::assertTrue($signer->isValid(Request::create($url)));
    }

    private function orderWithId(int $id): Order
    {
        $order = new Order();
        $property = new \ReflectionProperty(Order::class, 'id');
        $property->setValue($order, $id);

        return $order;
    }
}
