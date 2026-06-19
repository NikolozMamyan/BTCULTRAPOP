<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Service\AdminStockManager;
use App\Service\AdminStockProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/stock')]
final class StockController extends AbstractController
{
    #[Route('', name: 'app_admin_stock_index', methods: ['GET'])]
    public function index(AdminStockProvider $stock): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/stock/index.html.twig', [
            'admin_user' => $adminUser,
            'products' => $stock->products(),
        ]);
    }

    #[Route('/products/{id}', name: 'app_admin_stock_product_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateProduct(
        Product $product,
        Request $request,
        AdminStockManager $stock,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(
                ['message' => $translator->trans('admin.stock.error.login_required')],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (!$this->isCsrfTokenValid('admin_stock_update', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(
                ['message' => $translator->trans('admin.product.flash.invalid_csrf')],
                Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $payload = $this->jsonPayload($request);
            $quantity = $payload['quantity'] ?? '';
            $updatedProduct = $stock->updateProduct(
                $product,
                \is_int($quantity) || \is_string($quantity) ? (string) $quantity : '',
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['message' => $translator->trans($exception->getMessage())],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->json([
            'message' => $translator->trans('admin.stock.flash.product_updated'),
            'product' => $updatedProduct,
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

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($payload) ? $payload : [];
    }
}
