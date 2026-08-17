<?php

namespace App\Controller\Front;

use App\Entity\Order;
use App\Entity\User;
use App\Exception\StripeConfigurationException;
use App\Repository\OrderRepository;
use App\Service\CartResolver;
use App\Service\OrderCartRecoveryManager;
use App\Service\OrderPaymentLinkSigner;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderCartRecoveryController extends AbstractController
{
    #[Route('/checkout/cart/{id}', name: 'app_checkout_cart_recovery', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(
        Request $request,
        int $id,
        OrderRepository $orders,
        OrderPaymentLinkSigner $linkSigner,
        OrderCartRecoveryManager $cartRecovery,
        CartResolver $cartResolver,
    ): Response {
        if (!$linkSigner->isValid($request)
            || !$this->isCsrfTokenValid('checkout_cart_recovery_' . $id, $request->request->getString('_csrf_token'))
        ) {
            return $this->errorResponse('checkout.cart_recovery.invalid', Response::HTTP_GONE);
        }

        $order = $orders->find($id);

        if (!$order instanceof Order) {
            return $this->errorResponse('checkout.cart_recovery.invalid', Response::HTTP_GONE);
        }

        $user = $this->getAuthenticatedUser();

        if (!$this->canAccessCart($order, $user)) {
            return $this->errorResponse('checkout.cart_recovery.not_available', Response::HTTP_FORBIDDEN);
        }

        try {
            $activeCart = $cartResolver->resolve($request, $user);
            $cart = $cartRecovery->reopen($order, $activeCart);
        } catch (StripeConfigurationException|ApiErrorException) {
            return $this->errorResponse('checkout.cart_recovery.temporary', Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        $this->addFlash('success', 'checkout.cart_recovery.flash.reopened');
        $response = $this->redirectToRoute('app_front_cart', [
            'reprise' => $order->getOrderNumber(),
        ]);
        $response->headers->setCookie($cartResolver->createCookie($cart, $request));

        return $response;
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canAccessCart(Order $order, ?User $user): bool
    {
        $orderUser = $order->getUser();

        return !$orderUser instanceof User
            || ($user instanceof User && $orderUser->getId() === $user->getId());
    }

    private function errorResponse(string $message, int $status): Response
    {
        $response = $this->render('front/checkout/recovery_unavailable.html.twig', [
            'message' => $message,
        ]);
        $response->setStatusCode($status);

        return $response;
    }
}
