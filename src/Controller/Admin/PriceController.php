<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Service\AdminPriceManager;
use App\Service\AdminPriceProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/prices')]
final class PriceController extends AbstractController
{
    #[Route('', name: 'app_admin_prices_index', methods: ['GET'])]
    public function index(AdminPriceProvider $prices): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/prices/index.html.twig', [
            'admin_user' => $adminUser,
            ...$prices->page(),
        ]);
    }

    #[Route('/products/{id}', name: 'app_admin_prices_product_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateProduct(
        Product $product,
        Request $request,
        AdminPriceManager $prices,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(['message' => $translator->trans('admin.price.error.login_required')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isValidPriceToken($request)) {
            return $this->json(['message' => $translator->trans('admin.product.flash.invalid_csrf')], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->jsonPayload($request);
            $updatedProduct = $prices->updateProduct(
                $product,
                (string) ($payload['priceTaxExcluded'] ?? ''),
                (string) ($payload['taxRate'] ?? ''),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'message' => $translator->trans($exception->getMessage()),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'message' => $translator->trans('admin.price.flash.product_updated'),
            'product' => $updatedProduct,
        ]);
    }

    #[Route('/categories/{id}', name: 'app_admin_prices_category_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateCategory(
        Category $category,
        Request $request,
        AdminPriceManager $prices,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->resolveAdminUser() instanceof User) {
            return $this->json(['message' => $translator->trans('admin.price.error.login_required')], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isValidPriceToken($request)) {
            return $this->json(['message' => $translator->trans('admin.product.flash.invalid_csrf')], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->jsonPayload($request);
            $updatedProducts = $prices->updateCategory(
                $category,
                (string) ($payload['priceTaxExcluded'] ?? ''),
                (string) ($payload['taxRate'] ?? ''),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'message' => $translator->trans($exception->getMessage()),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'message' => $translator->trans('admin.price.flash.category_updated', [
                '%count%' => \count($updatedProducts),
            ]),
            'products' => $updatedProducts,
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

    private function isValidPriceToken(Request $request): bool
    {
        return $this->isCsrfTokenValid('admin_prices_update', $request->headers->get('X-CSRF-Token', ''));
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
