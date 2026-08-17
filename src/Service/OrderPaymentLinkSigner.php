<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class OrderPaymentLinkSigner
{
    private const RECOVERY_TTL = 'P7D';
    private const CHECKOUT_RETURN_TTL = 'P2D';

    private UriSigner $signer;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.secret%')]
        string $secret,
    ) {
        $this->signer = new UriSigner($secret, '_signature', '_expires');
    }

    public function recoveryUrl(Order $order): string
    {
        return $this->signedOrderUrl('app_checkout_payment_recovery', $order, self::RECOVERY_TTL);
    }

    public function checkoutCancelUrl(Order $order): string
    {
        return $this->signedOrderUrl('app_checkout_cancel', $order, self::CHECKOUT_RETURN_TTL);
    }

    public function cartRecoveryUrl(Order $order): string
    {
        return $this->signedOrderUrl('app_checkout_cart_recovery', $order, self::CHECKOUT_RETURN_TTL);
    }

    public function isValid(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }

    private function signedOrderUrl(string $route, Order $order, string $ttl): string
    {
        $orderId = $order->getId();

        if (null === $orderId) {
            throw new \LogicException('The order must be persisted before generating a payment link.');
        }

        $url = $this->urlGenerator->generate(
            $route,
            ['id' => $orderId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->signer->sign($url, new \DateInterval($ttl));
    }
}
