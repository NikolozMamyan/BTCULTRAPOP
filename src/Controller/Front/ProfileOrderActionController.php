<?php

namespace App\Controller\Front;

use App\Entity\Order;
use App\Entity\User;
use App\Exception\StripeConfigurationException;
use App\Repository\OrderRepository;
use App\Service\CartResolver;
use App\Service\OrderCartRecoveryManager;
use App\Service\OrderPaymentLinkSigner;
use App\Service\OrderReorderManager;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil/commandes')]
final class ProfileOrderActionController extends AbstractController
{
    #[Route('/{id}/reprendre-panier', name: 'app_front_profile_order_cart_recovery', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reopenCart(
        Request $request,
        int $id,
        OrderRepository $orders,
        OrderCartRecoveryManager $cartRecovery,
        CartResolver $cartResolver,
    ): Response {
        $user = $this->ownedOrderUser($orders, $id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid(
            'profile_order_cart_recovery_' . $id,
            $request->request->getString('_csrf_token'),
        )) {
            $this->addFlash('error', 'profile.order_action.invalid_csrf');

            return $this->redirectToRoute('app_front_profil', ['_fragment' => 'profile-orders']);
        }

        $order = $orders->find($id);
        \assert($order instanceof Order);

        try {
            $activeCart = $cartResolver->resolve($request, $user);
            $cart = $cartRecovery->reopen($order, $activeCart);
        } catch (StripeConfigurationException|ApiErrorException) {
            $this->addFlash('error', 'checkout.cart_recovery.temporary');

            return $this->redirectToRoute('app_front_profil', ['_fragment' => 'profile-orders']);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_front_profil', ['_fragment' => 'profile-orders']);
        }

        $this->addFlash('success', 'checkout.cart_recovery.flash.reopened');
        $response = $this->redirectToRoute('app_front_cart', [
            'reprise' => $order->getOrderNumber(),
        ]);
        $response->headers->setCookie($cartResolver->createCookie($cart, $request));

        return $response;
    }

    #[Route('/{id}/racheter', name: 'app_front_profile_order_reorder', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reorder(
        Request $request,
        int $id,
        OrderRepository $orders,
        OrderReorderManager $reorderManager,
        OrderPaymentLinkSigner $paymentLinkSigner,
    ): Response {
        $user = $this->ownedOrderUser($orders, $id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid(
            'profile_order_reorder_' . $id,
            $request->request->getString('_csrf_token'),
        )) {
            $this->addFlash('error', 'profile.order_action.invalid_csrf');

            return $this->redirectToRoute('app_front_profil', ['_fragment' => 'profile-orders']);
        }

        $source = $orders->find($id);
        \assert($source instanceof Order);

        try {
            $newOrder = $reorderManager->createCheckoutOrder($source, $user);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_front_profil', ['_fragment' => 'profile-orders']);
        }

        return $this->redirect($paymentLinkSigner->recoveryUrl($newOrder), Response::HTTP_SEE_OTHER);
    }

    private function ownedOrderUser(OrderRepository $orders, int $id): ?User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('error', 'profile.order_action.login_required');

            return null;
        }

        $order = $orders->find($id);

        if (!$order instanceof Order || $order->getUser()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'profile.order_action.not_found');

            return null;
        }

        return $user;
    }
}
