<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\CustomerType;
use App\Repository\EmailTemplateRepository;
use App\Service\AdminCartManager;
use App\Service\AdminCartProvider;
use App\Service\AdminCartRecoveryManager;
use App\Service\AdminCustomerManager;
use App\Service\AdminCustomerProvider;
use App\Service\AdminEmailingManager;
use App\Service\AdminVisitorProvider;
use App\Service\AssetUrlResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/clients')]
final class CustomerController extends AbstractController
{
    #[Route('', name: 'app_admin_customers_index', methods: ['GET'])]
    public function index(Request $request, AdminCustomerProvider $customers): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $search = trim($request->query->getString('q'));

        return $this->render('admin/customers/index.html.twig', [
            'admin_user' => $adminUser,
            'search' => $search,
            ...$customers->index($search),
        ]);
    }

    #[Route('/emailing', name: 'app_admin_customers_emailing', methods: ['GET', 'POST'])]
    public function emailing(
        Request $request,
        EmailTemplateRepository $emailTemplates,
        AdminEmailingManager $emailingManager,
        AssetUrlResolver $assetUrlResolver,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $formData = [
            'template_name' => '',
            'subject' => '',
            'selected_user_ids' => [],
            'manual_emails' => [],
            'html_content' => $this->defaultEmailHtml(
                $assetUrlResolver->resolveAbsolute('img/logo/logotype-white.svg') ?? 'https://ultrapop.com/assets/img/logo/logotype-white-TylwvbF.svg',
            ),
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'template_name' => $request->request->getString('template_name'),
                'subject' => $request->request->getString('subject'),
                'selected_user_ids' => array_map('strval', $request->request->all('selected_user_ids')),
                'manual_emails' => array_map('strval', $request->request->all('manual_emails')),
                'html_content' => $request->request->getString('html_content'),
            ];

            if (!$this->isCsrfTokenValid('admin_customer_emailing', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'admin.emailing.flash.invalid_csrf');

                return $this->redirectToRoute('app_admin_customers_emailing');
            }

            try {
                $result = $emailingManager->createAndSend($adminUser, $formData);
                $this->addFlash('success', 'admin.emailing.flash.sent');

                return $this->redirectToRoute('app_admin_customers_emailing', [
                    'sent' => $result['recipient_count'],
                ]);
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (TransportExceptionInterface $exception) {
                $this->addFlash('error', 'admin.emailing.flash.transport_error');
            }
        }

        return $this->render('admin/customers/emailing.html.twig', [
            'admin_user' => $adminUser,
            'templates' => $emailTemplates->findLatestForAdmin(),
            'recipient_choices' => $emailingManager->recipientChoices(),
            'form_data' => $formData,
            'sent_count' => max(0, $request->query->getInt('sent')),
        ]);
    }

    #[Route('/paniers', name: 'app_admin_customers_carts', methods: ['GET'])]
    public function carts(Request $request, AdminCartProvider $carts): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/customers/carts.html.twig', [
            'admin_user' => $adminUser,
            ...$carts->index($request->query->getString('filter', 'all')),
        ]);
    }

    #[Route('/paniers/{id}/relance', name: 'app_admin_customers_carts_recover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function recoverCart(
        int $id,
        Request $request,
        AdminCartRecoveryManager $cartRecoveryManager,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid('admin_cart_recovery_' . $id, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.cart.recovery.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_customers_carts', [
                'filter' => $request->query->getString('filter', 'abandoned'),
            ]);
        }

        try {
            $result = $cartRecoveryManager->sendReminder($id);
            $this->addFlash('success', 'admin.cart.recovery.flash.sent');

            return $this->redirectToRoute('app_admin_customers_carts', [
                'filter' => $request->request->getString('filter', 'abandoned'),
                'sent_to' => $result['email'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (TransportExceptionInterface) {
            $this->addFlash('error', 'admin.cart.recovery.flash.transport_error');
        }

        return $this->redirectToRoute('app_admin_customers_carts', [
            'filter' => $request->request->getString('filter', 'abandoned'),
        ]);
    }

    #[Route('/paniers/{id}/supprimer', name: 'app_admin_customers_carts_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCart(
        int $id,
        Request $request,
        AdminCartManager $cartManager,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $filter = $request->request->getString('filter', 'all');

        if (!$this->isCsrfTokenValid('admin_cart_delete_' . $id, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'admin.cart.delete.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_customers_carts', ['filter' => $filter]);
        }

        try {
            $cartManager->delete($id);
            $this->addFlash('success', 'admin.cart.delete.flash.deleted');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_customers_carts', ['filter' => $filter]);
    }

    #[Route('/viewer', name: 'app_admin_customers_viewer', methods: ['GET'])]
    public function viewer(AdminVisitorProvider $visitors): Response
    {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        return $this->render('admin/customers/viewer.html.twig', [
            'admin_user' => $adminUser,
            ...$visitors->online(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_customers_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $customer,
        Request $request,
        AdminCustomerProvider $customers,
        AdminCustomerManager $manager,
    ): Response {
        $adminUser = $this->resolveAdminUser();

        if (!$adminUser instanceof User) {
            return $this->redirectToRoute('app_front_profil');
        }

        $isCurrentAdmin = $customer->getId() === $adminUser->getId();
        $form = $this->createForm(CustomerType::class, $customer, [
            'admin_role' => in_array('ROLE_ADMIN', $customer->getRoles(), true),
            'is_current_admin' => $isCurrentAdmin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($customer, (bool) $form->get('adminRole')->getData(), $adminUser);
            $this->addFlash('success', 'admin.customer.flash.updated');

            return $this->redirectToRoute('app_admin_customers_edit', ['id' => $customer->getId()]);
        }

        return $this->render('admin/customers/edit.html.twig', [
            'admin_user' => $adminUser,
            'customer' => $customer,
            'customer_view' => $customers->show($customer),
            'form' => $form,
            'is_current_admin' => $isCurrentAdmin,
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

    private function defaultEmailHtml(string $logoUrl): string
    {
        $logoUrl = htmlspecialchars($logoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:28px 16px;font-family:Arial,sans-serif;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:20px;overflow:hidden;">
        <tr>
          <td style="background:#203263;padding:24px 30px;">
            <a href="https://ultrapop.com" style="display:inline-block;text-decoration:none;">
              <img src="{$logoUrl}" width="176" alt="ULTRAPOP" style="display:block;width:176px;max-width:100%;height:auto;border:0;">
            </a>
          </td>
        </tr>
        <tr>
          <td style="padding:34px 30px;color:#203263;">
            <p style="margin:0 0 8px;color:#e82118;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;">Nouvelle campagne</p>
            <h1 style="margin:0 0 16px;font-size:30px;line-height:1.15;">Titre de ton email</h1>
            <p style="margin:0 0 22px;color:#475467;font-size:16px;line-height:1.7;">
              Ajoute ici ton contenu HTML. Tu peux intégrer des images publiques, des boutons et tes sections marketing.
            </p>
            <a href="https://ultrapop.com/boutique" style="display:inline-block;padding:14px 22px;border-radius:999px;background:#e82118;color:#ffffff;text-decoration:none;font-weight:900;">Découvrir la boutique</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }
}
