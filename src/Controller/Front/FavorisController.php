<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FavorisController extends AbstractController
{
    #[Route('/favoris', name: 'app_front_favoris', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('front/favoris/index.html.twig', [
            'products' => $user instanceof User ? $catalog->favorites($user) : [],
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
