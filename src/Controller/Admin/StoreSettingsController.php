<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\StoreSettingsType;
use App\Service\StoreSettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/parametres-boutique')]
final class StoreSettingsController extends AbstractController
{
    #[Route('', name: 'app_admin_store_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, StoreSettingsManager $manager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $settings = $manager->getSettings();
        $form = $this->createForm(StoreSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($settings);
            $this->addFlash('success', 'admin.store_settings.flash.updated');

            return $this->redirectToRoute('app_admin_store_settings');
        }

        return $this->render('admin/store_settings/index.html.twig', [
            'admin_user' => $adminUser,
            'settings' => $settings,
            'form' => $form,
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
