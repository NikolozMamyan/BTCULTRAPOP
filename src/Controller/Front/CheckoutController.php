<?php

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\CartStatus;
use App\Enum\OrderStatus;
use App\Exception\StripeConfigurationException;
use App\Form\CheckoutAddressType;
use App\Model\CheckoutAddress;
use App\Repository\OrderRepository;
use App\Service\CartResolver;
use App\Service\CartViewBuilder;
use App\Service\OrderManager;
use App\Service\StripeCheckoutService;
use App\Service\StripeWebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout')]
final class CheckoutController extends AbstractController
{
    #[Route('/stripe', name: 'app_checkout_stripe_create', methods: ['POST'])]
    public function createStripeSession(
        Request $request,
        CartResolver $cartResolver,
        CartViewBuilder $cartViewBuilder,
        OrderManager $orderManager,
        StripeCheckoutService $stripeCheckout,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $cart = $cartResolver->resolve($request, $user);

        if (!$cart instanceof Cart || 0 === $cart->getItems()->count()) {
            $this->addFlash('error', 'checkout.flash.empty_cart');

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
            $order = $orderManager->createGuestFromCart(
                cart: $cart,
                shippingAddress: $address,
                user: $user,
            );
            $entityManager->persist($order);
            $entityManager->flush();

            $session = $stripeCheckout->createSession($order);
            $order
                ->setStripeCheckoutSessionId($session->id)
                ->setStripePaymentIntentId($this->stripeObjectId($session->payment_intent ?? null))
                ->setStripeCustomerId($this->stripeObjectId($session->customer ?? null));
            $entityManager->flush();

            return $this->redirect((string) $session->url, Response::HTTP_SEE_OTHER);
        } catch (StripeConfigurationException) {
            $this->restoreCartAfterCheckoutFailure($cart, $order);
            $entityManager->flush();
            $this->addFlash('error', 'checkout.flash.stripe_not_configured');
        } catch (ApiErrorException) {
            $this->restoreCartAfterCheckoutFailure($cart, $order);
            $entityManager->flush();
            $this->addFlash('error', 'checkout.flash.stripe_error');
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'checkout.flash.invalid_cart');
        }

        return $this->redirectToRoute('app_front_cart');
    }

    #[Route('/success', name: 'app_checkout_success', methods: ['GET'])]
    public function success(
        Request $request,
        StripeCheckoutService $stripeCheckout,
        StripeWebhookHandler $stripeWebhookHandler,
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
        ]);
    }

    #[Route('/cancel', name: 'app_checkout_cancel', methods: ['GET'])]
    public function cancel(Request $request, OrderRepository $orders, EntityManagerInterface $entityManager): Response
    {
        $order = null;
        $orderNumber = trim($request->query->getString('order'));

        if ('' !== $orderNumber) {
            $order = $orders->findOneBy(['orderNumber' => $orderNumber]);

            if ($order instanceof Order && OrderStatus::PENDING_PAYMENT === $order->getStatus()) {
                $order->cancel();
                $entityManager->flush();
            }
        }

        return $this->render('front/checkout/cancel.html.twig', [
            'order' => $order,
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function restoreCartAfterCheckoutFailure(Cart $cart, ?Order $order): void
    {
        $cart->setStatus(CartStatus::ACTIVE);
        $order?->cancel();
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
