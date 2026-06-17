<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Service\AdminOrderProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/orders')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'app_admin_orders_index', methods: ['GET'])]
    public function index(Request $request, AdminOrderProvider $orders): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));
        $status = trim($request->query->getString('status'));
        $paymentStatus = trim($request->query->getString('payment_status'));

        return $this->render('admin/orders/index.html.twig', [
            'admin_user' => $adminUser,
            'search' => $search,
            'selected_status' => $status,
            'selected_payment_status' => $paymentStatus,
            ...$orders->index($search, $status, $paymentStatus),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_orders_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order, AdminOrderProvider $orders): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/orders/show.html.twig', [
            'admin_user' => $adminUser,
            'order' => $orders->show($order),
        ]);
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
