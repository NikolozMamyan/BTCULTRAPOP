<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Form\Admin\ManualOrderType;
use App\Form\Admin\OrderExportType;
use App\Form\Admin\OrderStatusType;
use App\Model\AdminManualOrderData;
use App\Model\AdminManualOrderItemData;
use App\Model\AdminOrderExportData;
use App\Repository\OrderRepository;
use App\Service\AdminOrderCsvExporter;
use App\Service\AdminOrderManager;
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

    #[Route('/new', name: 'app_admin_orders_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AdminOrderManager $manager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $data = new AdminManualOrderData();
        $data->items[] = new AdminManualOrderItemData();
        $form = $this->createForm(ManualOrderType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $order = $manager->createManual($data);
                $this->addFlash('success', 'admin.order.manual.flash.created');

                return $this->redirectToRoute('app_admin_orders_show', ['id' => $order->getId()]);
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        $response = $this->render('admin/orders/new.html.twig', [
            'admin_user' => $adminUser,
            'form' => $form->createView(),
        ]);

        if ($form->isSubmitted()) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    #[Route('/export', name: 'app_admin_orders_export', methods: ['GET', 'POST'])]
    public function export(
        Request $request,
        OrderRepository $orders,
        AdminOrderCsvExporter $exporter,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $data = new AdminOrderExportData();
        $preselectedOrderId = $request->query->getInt('order');

        if ($preselectedOrderId > 0) {
            $preselectedOrder = $orders->find($preselectedOrderId);

            if ($preselectedOrder instanceof Order) {
                $data->orders = [$preselectedOrder];
            }
        }

        $form = $this->createForm(OrderExportType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $exporter->response($data->orders);
        }

        $response = $this->render('admin/orders/export.html.twig', [
            'admin_user' => $adminUser,
            'form' => $form->createView(),
        ]);

        if ($form->isSubmitted()) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
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
            'status_form' => $this->createForm(OrderStatusType::class, [
                'status' => $order->getStatus(),
            ], [
                'action' => $this->generateUrl('app_admin_orders_status', ['id' => $order->getId()]),
                'method' => 'POST',
            ])->createView(),
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_orders_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Request $request, Order $order, AdminOrderManager $manager): Response
    {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $form = $this->createForm(OrderStatusType::class, [
            'status' => $order->getStatus(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->updateStatus($order, $form->get('status')->getData());
                $this->addFlash('success', 'admin.order.status.flash.updated');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        } else {
            $this->addFlash('error', 'admin.order.status.error.invalid');
        }

        return $this->redirectToRoute('app_admin_orders_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_orders_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Order $order, AdminOrderManager $manager): Response
    {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid('admin_order_delete_' . $order->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'admin.order.delete.error.invalid_csrf');

            return $this->redirectToRoute('app_admin_orders_show', ['id' => $order->getId()]);
        }

        $manager->delete($order);
        $this->addFlash('success', 'admin.order.delete.flash.deleted');

        return $this->redirectToRoute('app_admin_orders_index');
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
