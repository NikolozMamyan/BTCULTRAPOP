<?php

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\CartStatus;
use App\Exception\StripeConfigurationException;
use App\Form\CheckoutAddressType;
use App\Model\CheckoutAddress;
use App\Repository\OrderRepository;
use App\Service\Analytics\EcommercePayloadBuilder;
use App\Service\CartManager;
use App\Service\CartResolver;
use App\Service\CartViewBuilder;
use App\Service\OrderManager;
use App\Service\OrderCartRecoveryManager;
use App\Service\OrderPaymentLinkSigner;
use App\Service\OrderPaymentRecoveryManager;
use App\Service\PromoCodeManager;
use App\Service\ShippingRateCalculator;
use App\Service\StripeCheckoutService;
use App\Service\StripeWebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/checkout')]
final class CheckoutController extends AbstractController
{
    #[Route('/stripe', name: 'app_checkout_stripe_create', methods: ['POST'])]
    public function createStripeSession(
        Request $request,
        CartResolver $cartResolver,
        CartManager $cartManager,
        CartViewBuilder $cartViewBuilder,
        OrderManager $orderManager,
        ShippingRateCalculator $shippingRateCalculator,
        PromoCodeManager $promoCodeManager,
        StripeCheckoutService $stripeCheckout,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $cart = $cartResolver->resolve($request, $user);

        if (!$cart instanceof Cart || 0 === $cart->getItems()->count()) {
            $this->addFlash('error', 'checkout.flash.empty_cart');

            return $this->redirectToRoute('app_front_cart');
        }

        $cartManager->refreshPrices($cart);
        $entityManager->flush();

        try {
            $cartManager->assertAvailableForCheckout($cart);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_front_cart');
        }

        $shippingQuote = $shippingRateCalculator->quote($cart->getTotalTaxIncludedCents());

        if (!$shippingQuote['minimumReached']) {
            $this->addFlash('error', $translator->trans('checkout.flash.minimum_order', [
                '%minimum%' => number_format($shippingQuote['minimumOrderCents'] / 100, 2, ',', ' ') . ' €',
            ]));

            return $this->redirectToRoute('app_front_cart');
        }

        $address = CheckoutAddress::fromUser($user);
        $form = $this->createForm(CheckoutAddressType::class, $address, [
            'action' => $this->generateUrl('app_checkout_stripe_create'),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $response = $this->render('front/cart/index.html.twig', [
                'cart' => $cartViewBuilder->build($cart),
                'checkout_form' => $form->createView(),
                'checkout_address' => null,
                'checkout_address_saved' => false,
            ]);
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $response;
        }

        if (!$stripeCheckout->isConfigured()) {
            $this->addFlash('error', 'checkout.flash.stripe_not_configured');

            return $this->redirectToRoute('app_front_cart');
        }

        $order = null;

        try {
            $discountAmount = $promoCodeManager->discountForCart($cart, true);
            $order = $orderManager->createGuestFromCart(
                cart: $cart,
                shippingAddress: $address,
                user: $user,
                customerEmail: $address->email,
                shippingAmountTaxIncludedCents: $shippingQuote['amountCents'],
                discountAmountTaxIncludedCents: $discountAmount,
            );
            $entityManager->persist($order);
            $entityManager->flush();

            $session = $stripeCheckout->createSession($order);
            $order
                ->setStripeCheckoutSessionId($session->id)
                ->setStripePaymentIntentId($this->stripeObjectId($session->payment_intent ?? null))
                ->setStripeCustomerId($this->stripeObjectId($session->customer ?? null))
                ->markPaymentProcessing();
            $entityManager->flush();

            return $this->redirect((string) $session->url, Response::HTTP_SEE_OTHER);
        } catch (StripeConfigurationException) {
            $this->restoreCartAfterCheckoutFailure($cart, $order, $orderManager);
            $entityManager->flush();
            $this->addFlash('error', 'checkout.flash.stripe_not_configured');
        } catch (ApiErrorException) {
            $this->restoreCartAfterCheckoutFailure($cart, $order, $orderManager);
            $entityManager->flush();
            $this->addFlash('error', 'checkout.flash.stripe_error');
        } catch (\InvalidArgumentException $exception) {
            $this->restoreCartAfterCheckoutFailure($cart, $order, $orderManager);
            $entityManager->flush();
            $message = str_starts_with($exception->getMessage(), 'promo.')
                ? $exception->getMessage()
                : 'checkout.flash.invalid_cart';
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_front_cart');
    }

    #[Route('/success', name: 'app_checkout_success', methods: ['GET'])]
    public function success(
        Request $request,
        StripeCheckoutService $stripeCheckout,
        StripeWebhookHandler $stripeWebhookHandler,
        EcommercePayloadBuilder $analytics,
        EntityManagerInterface $entityManager,
    ): Response {
        $order = null;
        $sessionId = trim($request->query->getString('session_id'));

        if ('' !== $sessionId && $stripeCheckout->isConfigured()) {
            try {
                $session = $stripeCheckout->retrieveSession($sessionId);
                $order = $stripeWebhookHandler->synchronizeCheckoutSession($session);
                $entityManager->flush();
            } catch (StripeConfigurationException|ApiErrorException) {
                $this->addFlash('error', 'checkout.flash.verify_later');
            }
        }

        return $this->render('front/checkout/success.html.twig', [
            'order' => $order,
            'purchase_analytics' => $order instanceof Order ? $analytics->purchase($order) : null,
        ]);
    }

    #[Route('/cancel', name: 'app_checkout_cancel_legacy', methods: ['GET'])]
    #[Route('/cancel/{id}', name: 'app_checkout_cancel', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cancel(
        Request $request,
        OrderRepository $orders,
        OrderPaymentLinkSigner $paymentLinkSigner,
        OrderPaymentRecoveryManager $paymentRecovery,
        OrderCartRecoveryManager $cartRecovery,
    ): Response {
        $order = null;
        $orderId = $request->attributes->getInt('id');

        if ($orderId > 0 && $paymentLinkSigner->isValid($request)) {
            $candidate = $orders->find($orderId);
            $order = $candidate instanceof Order ? $candidate : null;
        }

        $user = $this->getAuthenticatedUser();
        $canRecoverCart = $order instanceof Order
            && $this->canAccessCart($order, $user)
            && $cartRecovery->status($order)['available'];

        return $this->render('front/checkout/cancel.html.twig', [
            'order' => $order,
            'payment_recovery_url' => $order instanceof Order
                ? $paymentRecovery->customerRecoveryUrl($order)
                : null,
            'cart_recovery_url' => $canRecoverCart
                ? $paymentLinkSigner->cartRecoveryUrl($order)
                : null,
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function restoreCartAfterCheckoutFailure(Cart $cart, ?Order $order, OrderManager $orderManager): void
    {
        $cart->setStatus(CartStatus::ACTIVE);

        if ($order instanceof Order) {
            $orderManager->cancel($order);
        }
    }

    private function canAccessCart(Order $order, ?User $user): bool
    {
        $orderUser = $order->getUser();

        return !$orderUser instanceof User
            || ($user instanceof User && $orderUser->getId() === $user->getId());
    }

    private function stripeObjectId(mixed $value): ?string
    {
        if (is_scalar($value) && '' !== trim((string) $value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $id = $value->id ?? null;

            return is_scalar($id) && '' !== trim((string) $id) ? (string) $id : null;
        }

        return null;
    }
}
