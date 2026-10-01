<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Exception\SageApiException;
use App\Service\OrderPreparationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/orders/{id}/preparation', requirements: ['id' => '\d+'])]
final class OrderPreparationController extends AbstractController
{
    #[Route('', name: 'app_admin_orders_preparation', methods: ['GET'])]
    public function index(Order $order, OrderPreparationManager $preparation): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        try {
            $view = $preparation->view($order);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_admin_orders_index');
        }

        return $this->render('admin/orders/preparation.html.twig', [
            'admin_user' => $adminUser,
            'order' => $view,
        ]);
    }

    #[Route('/scan', name: 'app_admin_orders_preparation_scan', methods: ['POST'])]
    public function scan(
        Order $order,
        Request $request,
        OrderPreparationManager $preparation,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.login_required')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->validToken($request, $order)) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.invalid_csrf')], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->toArray();

        try {
            $result = $preparation->scan($order, (string) ($payload['ean'] ?? ''));

            return $this->json([
                'ok' => true,
                ...$result,
                'message' => $translator->trans($result['message'], ['%product%' => $result['item']['name']]),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['ok' => false, 'message' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/items/{itemId}', name: 'app_admin_orders_preparation_quantity', requirements: ['itemId' => '\d+'], methods: ['POST'])]
    public function quantity(
        Order $order,
        int $itemId,
        Request $request,
        OrderPreparationManager $preparation,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.login_required')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->validToken($request, $order)) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.invalid_csrf')], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->findOrderItem($order, $itemId);

        if (!$item instanceof OrderItem) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.item_not_found')], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();

        try {
            return $this->json([
                'ok' => true,
                ...$preparation->updateQuantity($order, $item, (int) ($payload['quantity'] ?? -1)),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['ok' => false, 'message' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/complete', name: 'app_admin_orders_preparation_complete', methods: ['POST'])]
    public function complete(
        Order $order,
        Request $request,
        OrderPreparationManager $preparation,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.login_required')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->validToken($request, $order)) {
            return $this->json(['ok' => false, 'message' => $translator->trans('admin.order.preparation.error.invalid_csrf')], Response::HTTP_BAD_REQUEST);
        }

        try {
            $preparation->completeAndExport($order);

            return $this->json([
                'ok' => true,
                'message' => $translator->trans('admin.order.preparation.complete.success'),
                'redirectUrl' => $this->generateUrl('app_admin_orders_index'),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['ok' => false, 'message' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SageApiException $exception) {
            return $this->json(['ok' => false, 'message' => $translator->trans($exception->getMessage())], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function validToken(Request $request, Order $order): bool
    {
        return $this->isCsrfTokenValid(
            'admin_order_preparation_' . $order->getId(),
            $request->headers->get('X-CSRF-Token', ''),
        );
    }

    private function findOrderItem(Order $order, int $itemId): ?OrderItem
    {
        foreach ($order->getItems() as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    private function resolveAdminUser(): ?User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Admin access is required.');
        }

        return $user;
    }
}
