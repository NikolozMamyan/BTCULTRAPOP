<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\User;
use App\Form\Admin\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\AdminCategoryManager;
use App\Service\CatalogIconUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories')]
final class CategoryController extends AbstractController
{
    #[Route('', name: 'app_admin_categories_index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $categories): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));

        return $this->render('admin/categories/index.html.twig', [
            'admin_user' => $adminUser,
            'categories' => $categories->findForAdmin($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_admin_categories_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        AdminCategoryManager $categoryManager,
        CategoryRepository $categories,
    ): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category, [
            'parent_choices' => $categories->findParentChoices($category),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categoryManager->save($category);
            $this->addFlash('success', 'admin.category.flash.created');

            return $this->redirectToRoute('app_admin_categories_edit', ['id' => $category->getId()]);
        }

        return $this->render('admin/categories/new.html.twig', [
            'admin_user' => $adminUser,
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_categories_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Category $category,
        Request $request,
        AdminCategoryManager $categoryManager,
        CategoryRepository $categories,
    ): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $form = $this->createForm(CategoryType::class, $category, [
            'parent_choices' => $categories->findParentChoices($category),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categoryManager->save($category);
            $this->addFlash('success', $category->isActive() ? 'admin.category.flash.updated_with_products_enabled' : 'admin.category.flash.updated_with_products_disabled');

            return $this->redirectToRoute('app_admin_categories_edit', ['id' => $category->getId()]);
        }

        return $this->render('admin/categories/edit.html.twig', [
            'admin_user' => $adminUser,
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_categories_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Category $category, Request $request, AdminCategoryManager $categoryManager): JsonResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->json(['error' => 'admin.category.flash.login_required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid(sprintf('admin_category_toggle_%d', $category->getId()), $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'admin.category.flash.invalid_csrf'], Response::HTTP_BAD_REQUEST);
        }

        $category->setActive(!$category->isActive());
        $categoryManager->save($category);

        return $this->json([
            'active' => $category->isActive(),
            'label' => $category->isActive() ? 'admin.category.active.enabled' : 'admin.category.active.disabled',
            'message' => $category->isActive() ? 'admin.category.flash.enabled' : 'admin.category.flash.disabled',
        ]);
    }

    #[Route('/{id}/icon', name: 'app_admin_categories_icon', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function icon(
        Category $category,
        Request $request,
        AdminCategoryManager $categoryManager,
        CatalogIconUploader $iconUploader,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(sprintf('admin_category_icon_%d', $category->getId()), $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.catalog_icon.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_categories_index');
        }

        $file = $request->files->get('icon');

        try {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('admin.catalog_icon.flash.no_file');
            }

            $previousPath = $category->getIconPath();
            $category->setIconPath($iconUploader->upload('category', $category->getName(), $file));
            $categoryManager->save($category);
            $iconUploader->remove($previousPath);
            $this->addFlash('success', 'admin.catalog_icon.flash.updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_categories_index');
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
