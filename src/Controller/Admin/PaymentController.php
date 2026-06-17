<?php

namespace App\Controller\Admin;

use App\Entity\PaymentSettings;
use App\Entity\User;
use App\Service\PaymentSettingsManager;
use App\Service\StripeConfigProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/payments')]
final class PaymentController extends AbstractController
{
    #[Route('', name: 'app_admin_payments_index', methods: ['GET'])]
    public function index(PaymentSettingsManager $paymentSettingsManager, StripeConfigProvider $stripeConfig): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/payments/index.html.twig', [
            'admin_user' => $adminUser,
            'settings' => $paymentSettingsManager->getSettings(),
            'configured_modes' => $stripeConfig->configuredModes(),
            'configured_webhooks' => $stripeConfig->configuredWebhooks(),
        ]);
    }

    #[Route('/stripe-mode', name: 'app_admin_payments_stripe_mode', methods: ['POST'])]
    public function stripeMode(Request $request, PaymentSettingsManager $paymentSettingsManager, StripeConfigProvider $stripeConfig): RedirectResponse
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid('admin_payment_mode', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.payment.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_payments_index');
        }

        $mode = $request->request->getString('mode');

        if (!in_array($mode, [PaymentSettings::MODE_SANDBOX, PaymentSettings::MODE_LIVE], true)) {
            $this->addFlash('error', 'admin.payment.flash.invalid_mode');

            return $this->redirectToRoute('app_admin_payments_index');
        }

        if (!($stripeConfig->configuredModes()[$mode] ?? false)) {
            $this->addFlash('error', 'admin.payment.flash.missing_secret');

            return $this->redirectToRoute('app_admin_payments_index');
        }

        $paymentSettingsManager->setStripeMode($mode);
        $this->addFlash('success', 'admin.payment.flash.mode_updated');

        if (!($stripeConfig->configuredWebhooks()[$mode] ?? false)) {
            $this->addFlash('error', 'admin.payment.flash.missing_webhook');
        }

        return $this->redirectToRoute('app_admin_payments_index');
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
