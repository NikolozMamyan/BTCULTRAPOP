<?php

namespace App\Controller\Front;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Service\ProfileOrderProvider;
use App\Service\UserAvatarUploader;
use App\Service\UserAddressManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'app_front_profil', methods: ['GET'])]
    public function index(ProfileOrderProvider $orderProvider): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('front/profil/index.html.twig', [
            'user_identifier' => $user?->getUserIdentifier(),
            'profile_orders' => $user instanceof User ? $orderProvider->forUser($user) : [],
        ]);
    }

    #[Route('/profil/adresses', name: 'app_front_profile_address_add', methods: ['POST'])]
    public function addAddress(
        Request $request,
        EntityManagerInterface $entityManager,
        UserAddressManager $addressManager,
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            $this->addFlash('error', 'profile.address.flash.login_required');

            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid('profile_address_add', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        $address = $addressManager->createAddress($user, $request->request->all());

        if (0 !== count($addressManager->validate($address))) {
            $this->addFlash('error', 'profile.address.flash.invalid');

            return $this->redirectToRoute('app_front_profil');
        }

        $entityManager->persist($address);
        $entityManager->flush();
        $this->addFlash('success', 'profile.address.flash.added');

        return $this->redirectToRoute('app_front_profil');
    }

    #[Route('/profil/adresses/{id}', name: 'app_front_profile_address_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateAddress(
        int $id,
        Request $request,
        AddressRepository $addresses,
        EntityManagerInterface $entityManager,
        UserAddressManager $addressManager,
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            $this->addFlash('error', 'profile.address.flash.login_required');

            return $this->redirectToRoute('app_front_profil');
        }

        $address = $addresses->find($id);

        if (!$address instanceof Address || $address->getUser()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'profile.address.flash.not_found');

            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid(sprintf('profile_address_update_%d', $address->getId()), $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        $addressManager->updateAddress($user, $address, $request->request->all());

        if (0 !== count($addressManager->validate($address))) {
            $this->addFlash('error', 'profile.address.flash.invalid');

            return $this->redirectToRoute('app_front_profil');
        }

        $entityManager->flush();
        $this->addFlash('success', 'profile.address.flash.updated');

        return $this->redirectToRoute('app_front_profil');
    }

    #[Route('/profil/avatar', name: 'app_front_profile_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(
        Request $request,
        EntityManagerInterface $entityManager,
        UserAvatarUploader $avatarUploader,
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            $this->addFlash('error', 'profile.avatar.flash.login_required');

            return $this->redirectToRoute('app_front_profil');
        }

        if (!$this->isCsrfTokenValid('profile_avatar_upload', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'auth.flash.invalid_csrf');

            return $this->redirectToRoute('app_front_profil');
        }

        $avatar = $request->files->get('avatar');

        if (!$avatar instanceof UploadedFile) {
            $this->addFlash('error', 'profile.avatar.flash.missing');

            return $this->redirectToRoute('app_front_profil');
        }

        try {
            $avatarUploader->upload($user, $avatar);
            $entityManager->flush();
            $this->addFlash('success', 'profile.avatar.flash.updated');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_front_profil');
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
