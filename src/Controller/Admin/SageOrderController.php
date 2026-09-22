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
            // dd($this->debugPayload($sageOrders->payload($order)));

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

    // /**
    //  * @param array<string, mixed> $payload
    //  *
    //  * @return array{referenceCommande: string, hasZRemise: bool, hasRemiseField: bool, lines: list<array<string, mixed>>, raw: array<string, mixed>}
    //  */
    // private function debugPayload(array $payload): array
    // {
    //     $lines = array_values(array_map(
    //         static fn (array $line): array => [
    //             'reference' => (string) ($line['reference'] ?? ''),
    //             'designation' => (string) ($line['designation'] ?? ''),
    //             'prixHT' => $line['prixHT'] ?? null,
    //             'quantite' => $line['quantite'] ?? null,
    //             'quantitePreparee' => $line['quantitePreparee'] ?? null,
    //             'remise' => $line['remise'] ?? null,
    //         ],
    //         array_filter($payload['orderLines'] ?? [], 'is_array'),
    //     ));

    //     return [
    //         'referenceCommande' => (string) ($payload['referenceCommande'] ?? ''),
    //         'hasZRemise' => count(array_filter($lines, static fn (array $line): bool => 'ZREMISE' === $line['reference'])) > 0,
    //         'hasRemiseField' => count(array_filter($lines, static fn (array $line): bool => null !== $line['remise'] && '' !== (string) $line['remise'])) > 0,
    //         'lines' => $lines,
    //         'raw' => $payload,
    //     ];
    // }

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
