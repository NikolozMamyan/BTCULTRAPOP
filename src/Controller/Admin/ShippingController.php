<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\ShippingSettingsType;
use App\Service\ShippingSettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/shipping')]
final class ShippingController extends AbstractController
{
    #[Route('', name: 'app_admin_shipping_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ShippingSettingsManager $manager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $settings = $manager->getSettings();
        $form = $this->createForm(ShippingSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($settings);
            $this->addFlash('success', 'admin.shipping.flash.updated');

            return $this->redirectToRoute('app_admin_shipping_index');
        }

        return $this->render('admin/shipping/index.html.twig', [
            'admin_user' => $adminUser,
            'settings' => $settings,
            'form' => $form->createView(),
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
