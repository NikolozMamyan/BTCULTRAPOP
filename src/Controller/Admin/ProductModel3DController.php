<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductModel3D;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductModel3DRepository;
use App\Repository\ProductRepository;
use App\Service\ProductModel3DManager;
use App\Service\ProductModel3DTypeGuesser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/modeles-3d')]
final class ProductModel3DController extends AbstractController
{
    #[Route('', name: 'app_admin_category_models_3d_index', methods: ['GET'])]
    public function index(
        CategoryRepository $categories,
        ProductRepository $products,
        ProductModel3DRepository $models,
        ProductModel3DTypeGuesser $typeGuesser,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $assignableCategories = $categories->findAssignable();
        $assignableProducts = $products->findForModel3DAdmin($assignableCategories);
        $productsByCategory = [];

        foreach ($assignableCategories as $category) {
            $categoryId = $category->getId();

            if (null !== $categoryId) {
                $productsByCategory[$categoryId] = [];
            }
        }

        foreach ($assignableProducts as $product) {
            $categoryId = $product->getCategory()?->getId();

            if (null !== $categoryId) {
                $productsByCategory[$categoryId][] = $product;
            }
        }

        return $this->render('admin/category_models_3d/index.html.twig', [
            'admin_user' => $adminUser,
            'categories' => $assignableCategories,
            'products_by_category' => $productsByCategory,
            'models' => $models->findIndexedByProduct($assignableProducts),
            'product_type_guesses' => $typeGuesser->guessIndexed($assignableProducts),
            'model_types' => ProductModel3D::TYPES,
            'default_model_type' => ProductModel3D::DEFAULT_MODEL_TYPE,
            'defaults' => ProductModel3D::DEFAULTS_BY_TYPE[ProductModel3D::DEFAULT_MODEL_TYPE],
            'defaults_by_type' => ProductModel3D::DEFAULTS_BY_TYPE,
            'limits' => ProductModel3D::LIMITS,
        ]);
    }

    #[Route('/produits/{id}', name: 'app_admin_product_models_3d_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Product $product, Request $request, ProductModel3DManager $manager): RedirectResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid(sprintf('admin_product_model_3d_%d', $product->getId()), $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.model_3d.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_category_models_3d_index');
        }

        try {
            $payload = $request->request->all('model_3d');
            $textureFile = $request->files->get('texture_image');
            $manager->save(
                $product,
                is_array($payload) ? $payload : [],
                $textureFile instanceof UploadedFile ? $textureFile : null,
            );
            $this->addFlash('success', 'admin.model_3d.flash.saved');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_category_models_3d_index');
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
