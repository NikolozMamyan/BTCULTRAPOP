<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Service\AssetUrlResolver;
use App\Service\StripeCheckoutService;
use App\Service\StripeConfigProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StripeCheckoutServiceTest extends TestCase
{
    public function testShippingLineUsesThePublicTruckImage(): void
    {
        $packages = $this->createMock(Packages::class);
        $packages
            ->expects(self::once())
            ->method('getUrl')
            ->with('img/checkout/shipping-truck.svg')
            ->willReturn('/assets/img/checkout/shipping-truck-hash.svg');
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://preprod.ultrapop.com/checkout/stripe'));
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects(self::once())
            ->method('trans')
            ->with('checkout.shipping_line')
            ->willReturn('Livraison');
        $stripeConfig = (new \ReflectionClass(StripeConfigProvider::class))->newInstanceWithoutConstructor();
        $service = new StripeCheckoutService(
            $stripeConfig,
            $this->createStub(UrlGeneratorInterface::class),
            $translator,
            new AssetUrlResolver($packages, $requestStack, 'https://ultrapop.com'),
        );
        $order = (new Order())
            ->setCurrency('EUR')
            ->setShippingAmountTaxIncludedCents(590);
        $method = new \ReflectionMethod($service, 'shippingLineItem');

        /** @var array<string, mixed> $lineItem */
        $lineItem = $method->invoke($service, $order);

        self::assertSame('eur', $lineItem['price_data']['currency']);
        self::assertSame(590, $lineItem['price_data']['unit_amount']);
        self::assertSame('Livraison', $lineItem['price_data']['product_data']['name']);
        self::assertSame(
            ['https://preprod.ultrapop.com/assets/img/checkout/shipping-truck-hash.svg'],
            $lineItem['price_data']['product_data']['images'],
        );
        self::assertSame(1, $lineItem['quantity']);
    }
}
