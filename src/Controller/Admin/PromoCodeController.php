<?php

namespace App\Controller\Admin;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Form\Admin\PromoCodeType;
use App\Repository\PromoCodeRepository;
use App\Service\AdminPromoCodeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/codes-promo')]
final class PromoCodeController extends AbstractController
{
    #[Route('', name: 'app_admin_promo_codes_index', methods: ['GET'])]
    public function index(Request $request, PromoCodeRepository $promoCodes): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));

        return $this->render('admin/promo_codes/index.html.twig', [
            'admin_user' => $adminUser,
            'promo_codes' => $promoCodes->findForAdmin($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_admin_promo_codes_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AdminPromoCodeManager $manager): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $promoCode = new PromoCode();
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($promoCode);
            $this->addFlash('success', 'admin.promo.flash.created');

            return $this->redirectToRoute('app_admin_promo_codes_edit', ['id' => $promoCode->getId()]);
        }

        return $this->render('admin/promo_codes/new.html.twig', [
            'admin_user' => $adminUser,
            'promo_code' => $promoCode,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_promo_codes_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        PromoCode $promoCode,
        Request $request,
        AdminPromoCodeManager $manager,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $form = $this->createForm(PromoCodeType::class, $promoCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($promoCode);
            $this->addFlash('success', 'admin.promo.flash.updated');

            return $this->redirectToRoute('app_admin_promo_codes_edit', ['id' => $promoCode->getId()]);
        }

        return $this->render('admin/promo_codes/edit.html.twig', [
            'admin_user' => $adminUser,
            'promo_code' => $promoCode,
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
