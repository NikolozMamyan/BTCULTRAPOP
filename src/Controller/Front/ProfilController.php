<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'app_front_profil', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/profil/index.html.twig', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
        ]);
    }
}
