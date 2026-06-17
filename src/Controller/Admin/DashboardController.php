<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AdminDashboardProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(AdminDashboardProvider $dashboardProvider): Response
    {
        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Admin access is required.');
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'admin_user' => $user,
            'dashboard' => $dashboardProvider->dashboard(),
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
