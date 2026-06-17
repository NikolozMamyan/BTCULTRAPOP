<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Form\Admin\ProductType;
use App\Repository\ProductRepository;
use App\Service\AdminProductManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products')]
final class ProductController extends AbstractController
{
    #[Route('', name: 'app_admin_products_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));

        return $this->render('admin/products/index.html.twig', [
            'admin_user' => $adminUser,
            'products' => $products->findForAdmin($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AdminProductManager $productManager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $product = new Product();
        $ean = trim($request->query->getString('ean'));

        if (preg_match('/^\d{8,13}$/', $ean)) {
            $product->setEan($ean);
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productManager->save($product, $form->get('coverImageUrl')->getData());
            $this->addFlash('success', 'admin.product.flash.created');

            return $this->redirectToRoute('app_admin_products_edit', ['id' => $product->getId()]);
        }

        return $this->render('admin/products/new.html.twig', [
            'admin_user' => $adminUser,
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_products_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Product $product, Request $request, AdminProductManager $productManager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $form = $this->createForm(ProductType::class, $product, [
            'cover_image_url' => $product->getCoverImage()?->getPath(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productManager->save($product, $form->get('coverImageUrl')->getData());
            $this->addFlash('success', 'admin.product.flash.updated');

            return $this->redirectToRoute('app_admin_products_edit', ['id' => $product->getId()]);
        }

        return $this->render('admin/products/edit.html.twig', [
            'admin_user' => $adminUser,
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/scan', name: 'app_admin_products_scan', methods: ['POST'])]
    public function scan(Request $request, ProductRepository $products): JsonResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->json(['error' => 'admin.product.scanner.login_required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('admin_product_scan', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'admin.product.flash.invalid_csrf'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true);
        $ean = trim((string) ($payload['ean'] ?? ''));

        if (!preg_match('/^\d{8,13}$/', $ean)) {
            return $this->json(['error' => 'admin.product.scanner.invalid_code'], Response::HTTP_BAD_REQUEST);
        }

        $product = $products->findOneByEanForAdmin($ean);

        if ($product instanceof Product) {
            return $this->json([
                'found' => true,
                'ean' => $ean,
                'productName' => $product->getName(),
                'redirectUrl' => $this->generateUrl('app_admin_products_edit', ['id' => $product->getId()]),
            ]);
        }

        return $this->json([
            'found' => false,
            'ean' => $ean,
            'createUrl' => $this->generateUrl('app_admin_products_new', ['ean' => $ean]),
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_products_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Product $product, Request $request, AdminProductManager $productManager): JsonResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->json(['error' => 'admin.product.scanner.login_required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid(sprintf('admin_product_toggle_%d', $product->getId()), $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'admin.product.flash.invalid_csrf'], Response::HTTP_BAD_REQUEST);
        }

        $product->setActive(!$product->isActive());
        $productManager->save($product, $product->getCoverImage()?->getPath());

        return $this->json([
            'active' => $product->isActive(),
            'label' => $product->isActive() ? 'admin.product.active.enabled' : 'admin.product.active.disabled',
            'message' => $product->isActive() ? 'admin.product.flash.enabled' : 'admin.product.flash.disabled',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_products_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Product $product, Request $request, AdminProductManager $productManager): RedirectResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid(sprintf('admin_product_delete_%d', $product->getId()), $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.product.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_products_index');
        }

        try {
            $productManager->delete($product);
            $this->addFlash('success', 'admin.product.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'admin.product.flash.delete_blocked');
        }

        return $this->redirectToRoute('app_admin_products_index');
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
