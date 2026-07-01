<?php

namespace App\Controller\Front;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserSessionManager;
use App\Service\Mailer\SimpleMailerService;
use App\Service\PasswordResetManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth')]
final class AuthController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_auth_forgot_password', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('front/auth/forgot_password.html.twig');
    }

    #[Route('/mot-de-passe-oublie', name: 'app_auth_forgot_password_submit', methods: ['POST'])]
    public function requestPasswordReset(
        Request $request,
        UserRepository $users,
        PasswordResetManager $passwordResetManager,
        SimpleMailerService $mailer,
        LoggerInterface $logger,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('auth_forgot_password', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_auth_forgot_password');
        }

        $user = $users->loadUserByIdentifier($request->request->getString('email'));

        if ($user instanceof User && $user->isActive()) {
            $token = $passwordResetManager->createToken($user);
            $resetUrl = $this->generateUrl(
                'app_auth_reset_password',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            try {
                $mailer->sendTemplateMessage(
                    subject: 'Réinitialisation de ton mot de passe ULTRAPOP',
                    htmlTemplate: 'emails/password_reset.html.twig',
                    context: [
                        'user' => $user,
                        'reset_url' => $resetUrl,
                        'expires_in' => '1 heure',
                    ],
                    textMessage: sprintf(
                        "Bonjour %s,\n\nTu peux réinitialiser ton mot de passe ULTRAPOP avec ce lien valable 1 heure :\n%s\n\nSi tu n'es pas à l'origine de cette demande, ignore simplement cet email.",
                        $user->getFirstName(),
                        $resetUrl,
                    ),
                    to: [$user->getEmail()],
                );
            } catch (TransportExceptionInterface $exception) {
                $logger->error('Unable to send the password reset email.', [
                    'user_id' => $user->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->addFlash('success', 'auth.reset.flash.request_sent');

        return $this->redirectToRoute('app_auth_forgot_password');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_auth_reset_password', requirements: ['token' => '[A-Fa-f0-9]{32}\.[A-Fa-f0-9]{64}'], methods: ['GET'])]
    public function resetPassword(string $token, PasswordResetManager $passwordResetManager): Response
    {
        if (!$passwordResetManager->isTokenValid(mb_strtolower($token))) {
            $this->addFlash('error', 'auth.reset.flash.invalid_token');

            return $this->redirectToRoute('app_auth_forgot_password');
        }

        return $this->render('front/auth/reset_password.html.twig', [
            'token' => mb_strtolower($token),
        ]);
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_auth_reset_password_submit', requirements: ['token' => '[A-Fa-f0-9]{32}\.[A-Fa-f0-9]{64}'], methods: ['POST'])]
    public function updatePassword(
        string $token,
        Request $request,
        PasswordResetManager $passwordResetManager,
        UserSessionManager $userSessionManager,
    ): RedirectResponse {
        $token = mb_strtolower($token);

        if (!$this->isCsrfTokenValid('auth_reset_password_'.$token, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_auth_reset_password', ['token' => $token]);
        }

        $password = $request->request->getString('password');
        $passwordConfirmation = $request->request->getString('password_confirmation');

        if (mb_strlen($password) < 8) {
            $this->addFlash('error', 'auth.flash.password_too_short');

            return $this->redirectToRoute('app_auth_reset_password', ['token' => $token]);
        }

        if ($password !== $passwordConfirmation) {
            $this->addFlash('error', 'auth.reset.flash.password_mismatch');

            return $this->redirectToRoute('app_auth_reset_password', ['token' => $token]);
        }

        if (!$passwordResetManager->resetPassword($token, $password)) {
            $this->addFlash('error', 'auth.reset.flash.invalid_token');

            return $this->redirectToRoute('app_auth_forgot_password');
        }

        $response = $this->redirectToRoute('app_front_profil');
        $response->headers->setCookie($userSessionManager->createExpiredCookie($request));
        $this->addFlash('success', 'auth.reset.flash.password_updated');

        return $response;
    }

    #[Route('/login', name: 'app_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        UserSessionManager $userSessionManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('auth_login', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        $user = $users->loadUserByIdentifier($request->request->getString('email'));
        $password = $request->request->getString('password');

        if (!$user instanceof User || !$user->isActive() || !$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'auth.flash.invalid_credentials');

            return $this->redirectToRoute('app_front_profil');
        }

        $response = $this->redirectToRoute($this->isAdmin($user) ? 'app_admin_dashboard' : 'app_front_boutique');
        $response->headers->setCookie($userSessionManager->createSession($user, $request));
        $this->addFlash('success', 'auth.flash.login_success');

        return $response;
    }

    #[Route('/register', name: 'app_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        UserSessionManager $userSessionManager,
        ValidatorInterface $validator,
        SimpleMailerService $mailer,
        LoggerInterface $logger,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('auth_register', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        if (!$request->request->getBoolean('accept_terms')) {
            $this->addFlash('error', 'auth.flash.terms_required');

            return $this->redirectToRoute('app_front_profil');
        }

        $email = $request->request->getString('email');
        $password = $request->request->getString('password');

        if (mb_strlen($password) < 8) {
            $this->addFlash('error', 'auth.flash.password_too_short');

            return $this->redirectToRoute('app_front_profil');
        }

        if ($users->loadUserByIdentifier($email) instanceof User) {
            $this->addFlash('error', 'auth.flash.email_exists');

            return $this->redirectToRoute('app_front_profil');
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($request->request->getString('first_name'))
            ->setLastName($request->request->getString('last_name'))
            ->setPreferredLocale($request->getLocale());
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $addressName = trim($request->request->getString('address_name'));

        $address = (new Address())
            ->setName('' === $addressName ? 'Livraison' : $addressName)
            ->setStreet($request->request->getString('street'))
            ->setPostalCode($request->request->getString('postal_code'))
            ->setCity($request->request->getString('city'))
            ->setCountryCode('FR')
            ->setDefaultAddress(true);
        $user->addAddress($address);

        $violations = [
            ...iterator_to_array($validator->validate($user)),
            ...iterator_to_array($validator->validate($address)),
        ];

        if ([] !== $violations) {
            $this->addFlash('error', 'auth.flash.invalid_registration');

            return $this->redirectToRoute('app_front_profil');
        }

        $entityManager->persist($user);
        $entityManager->flush();

        try {
            $mailer->sendTemplateMessage(
                subject: 'Bienvenue dans la communauté ULTRAPOP',
                htmlTemplate: 'emails/welcome.html.twig',
                context: [
                    'user' => $user,
                ],
                textMessage: sprintf(
                    "Bonjour %s,\n\nTon compte ULTRAPOP est maintenant créé. Tu peux retrouver nos boissons, snacks et produits sous licences directement sur la boutique.\n\nÀ bientôt sur ULTRAPOP !",
                    $user->getFirstName(),
                ),
                to: [$user->getEmail()],
            );
        } catch (TransportExceptionInterface $exception) {
            $logger->error('Unable to send the registration welcome email.', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);
        }

        $response = $this->redirectToRoute('app_front_profil');
        $response->headers->setCookie($userSessionManager->createSession($user, $request));
        $this->addFlash('success', 'auth.flash.register_success');

        return $response;
    }

    #[Route('/logout', name: 'app_auth_logout', methods: ['POST'])]
    public function logout(Request $request, UserSessionManager $userSessionManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('auth_logout', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        $response = $this->redirectToRoute('app_front_home');
        $response->headers->setCookie($userSessionManager->revokeCurrentSession($request));
        $this->addFlash('success', 'auth.flash.logout_success');

        return $response;
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
