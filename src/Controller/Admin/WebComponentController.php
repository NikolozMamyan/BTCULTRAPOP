<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\WebComponentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/web-components')]
final class WebComponentController extends AbstractController
{
    #[Route('', name: 'app_admin_web_components_index', methods: ['GET'])]
    public function index(WebComponentManager $webComponents): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/web_components/index.html.twig', [
            'admin_user' => $adminUser,
            'components' => $webComponents->home(),
        ]);
    }

    #[Route('/{section}/images', name: 'app_admin_web_components_upload', requirements: ['section' => 'hero|licenses'], methods: ['POST'])]
    public function upload(string $section, Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_upload_'.$section, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        try {
            $added = $webComponents->addImages($section, $this->uploadedImages($request));
            $this->addFlash($added > 0 ? 'success' : 'error', $added > 0 ? 'admin.web_components.flash.uploaded' : 'admin.web_components.flash.no_file');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/{section}/images/delete', name: 'app_admin_web_components_delete', requirements: ['section' => 'hero|licenses'], methods: ['POST'])]
    public function delete(string $section, Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $path = $request->request->getString('path');

        if (!$this->isCsrfTokenValid('admin_web_components_delete_'.$section.'_'.$path, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        $deleted = $webComponents->deleteImage($section, $path);
        $this->addFlash($deleted ? 'success' : 'error', $deleted ? 'admin.web_components.flash.deleted' : 'admin.web_components.flash.delete_missing');

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/hero/{index}/mobile-image', name: 'app_admin_web_components_hero_mobile_image', requirements: ['index' => '\d+'], methods: ['POST'])]
    public function heroMobileImage(int $index, Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_hero_mobile_'.$index, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        $file = $request->files->get('image');

        try {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('admin.web_components.flash.no_file');
            }

            $webComponents->updateHeroMobileImage($index, $file);
            $this->addFlash('success', 'admin.web_components.flash.hero_mobile_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/hero/mobile-breakpoint', name: 'app_admin_web_components_hero_mobile_breakpoint', methods: ['POST'])]
    public function heroMobileBreakpoint(Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_hero_mobile_breakpoint', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        try {
            $webComponents->updateHeroMobileBreakpoint($request->request->getString('mobile_breakpoint'));
            $this->addFlash('success', 'admin.web_components.flash.hero_breakpoint_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/newsletter/image', name: 'app_admin_web_components_newsletter_image', methods: ['POST'])]
    public function newsletterImage(Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_newsletter', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        $file = $request->files->get('image');

        try {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('admin.web_components.flash.no_file');
            }

            $webComponents->updateNewsletterImage($file);
            $this->addFlash('success', 'admin.web_components.flash.newsletter_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/boutique/{heroKey}/image', name: 'app_admin_web_components_boutique_hero_image', requirements: ['heroKey' => 'all|drinks|savory|sweet'], methods: ['POST'])]
    public function boutiqueHeroImage(string $heroKey, Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_boutique_'.$heroKey, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        $file = $request->files->get('image');

        try {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('admin.web_components.flash.no_file');
            }

            $webComponents->updateBoutiqueHeroImage($heroKey, $file);
            $this->addFlash('success', 'admin.web_components.flash.boutique_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/boutique/{heroKey}/mobile-image', name: 'app_admin_web_components_boutique_hero_mobile_image', requirements: ['heroKey' => 'all|drinks|savory|sweet'], methods: ['POST'])]
    public function boutiqueHeroMobileImage(string $heroKey, Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_boutique_mobile_'.$heroKey, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        $file = $request->files->get('image');

        try {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('admin.web_components.flash.no_file');
            }

            $webComponents->updateBoutiqueHeroMobileImage($heroKey, $file);
            $this->addFlash('success', 'admin.web_components.flash.boutique_mobile_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    #[Route('/boutique/mobile-breakpoint', name: 'app_admin_web_components_boutique_hero_mobile_breakpoint', methods: ['POST'])]
    public function boutiqueHeroMobileBreakpoint(Request $request, WebComponentManager $webComponents): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_web_components_boutique_mobile_breakpoint', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.web_components.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_web_components_index');
        }

        try {
            $webComponents->updateBoutiqueHeroMobileBreakpoint($request->request->getString('mobile_breakpoint'));
            $this->addFlash('success', 'admin.web_components.flash.boutique_breakpoint_updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_web_components_index');
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedImages(Request $request): array
    {
        $files = $request->files->get('images');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
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
