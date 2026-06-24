<?php

namespace App\Controller\Front;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth')]
final class AuthController extends AbstractController
{
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
