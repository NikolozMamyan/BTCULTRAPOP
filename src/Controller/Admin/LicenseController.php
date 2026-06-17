<?php

namespace App\Controller\Admin;

use App\Entity\License;
use App\Entity\User;
use App\Form\Admin\LicenseType;
use App\Repository\LicenseRepository;
use App\Service\AdminLicenseManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/licenses')]
final class LicenseController extends AbstractController
{
    #[Route('', name: 'app_admin_licenses_index', methods: ['GET'])]
    public function index(Request $request, LicenseRepository $licenses): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));

        return $this->render('admin/licenses/index.html.twig', [
            'admin_user' => $adminUser,
            'licenses' => $licenses->findForAdmin($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_admin_licenses_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AdminLicenseManager $licenseManager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $license = new License();
        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $licenseManager->save($license);
            $this->addFlash('success', 'admin.license.flash.created');

            return $this->redirectToRoute('app_admin_licenses_edit', ['id' => $license->getId()]);
        }

        return $this->render('admin/licenses/new.html.twig', [
            'admin_user' => $adminUser,
            'license' => $license,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_licenses_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(License $license, Request $request, AdminLicenseManager $licenseManager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $licenseManager->save($license);
            $this->addFlash('success', $license->isActive() ? 'admin.license.flash.updated_with_products_enabled' : 'admin.license.flash.updated_with_products_disabled');

            return $this->redirectToRoute('app_admin_licenses_edit', ['id' => $license->getId()]);
        }

        return $this->render('admin/licenses/edit.html.twig', [
            'admin_user' => $adminUser,
            'license' => $license,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_licenses_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(License $license, Request $request, AdminLicenseManager $licenseManager): JsonResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->json(['error' => 'admin.license.flash.login_required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid(sprintf('admin_license_toggle_%d', $license->getId()), $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'admin.license.flash.invalid_csrf'], Response::HTTP_BAD_REQUEST);
        }

        $license->setActive(!$license->isActive());
        $licenseManager->save($license);

        return $this->json([
            'active' => $license->isActive(),
            'label' => $license->isActive() ? 'admin.license.active.enabled' : 'admin.license.active.disabled',
            'message' => $license->isActive() ? 'admin.license.flash.enabled' : 'admin.license.flash.disabled',
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
