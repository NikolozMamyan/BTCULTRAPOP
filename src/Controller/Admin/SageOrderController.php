<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Exception\SageApiException;
use App\Service\AdminSageOrderManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/erp/bons-de-commandes')]
final class SageOrderController extends AbstractController
{
    #[Route('', name: 'app_admin_sage_orders_index', methods: ['GET'])]
    public function index(AdminSageOrderManager $sageOrders): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/sage_orders/index.html.twig', [
            'admin_user' => $adminUser,
            ...$sageOrders->index(),
        ]);
    }

    #[Route('/{id}/envoyer', name: 'app_admin_sage_orders_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(
        Order $order,
        Request $request,
        AdminSageOrderManager $sageOrders,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        try {
            $sageOrders->export($order);
            $message = 'admin.sage_order.flash.sent';

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $translator->trans($message),
                ]);
            }

            $this->addFlash('success', 'admin.sage_order.flash.sent');
        } catch (SageApiException $exception) {
            $message = $exception->getMessage();

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => false,
                    'message' => $translator->trans($message),
                ], Response::HTTP_BAD_GATEWAY);
            }

            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_sage_orders_index');
    }

    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
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
