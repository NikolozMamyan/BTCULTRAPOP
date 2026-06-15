<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FavorisController extends AbstractController
{
    #[Route('/favoris', name: 'app_front_favoris', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/favoris/index.html.twig');
    }
}
