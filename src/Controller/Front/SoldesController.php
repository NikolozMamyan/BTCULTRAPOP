<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SoldesController extends AbstractController
{
    #[Route('/soldes', name: 'app_front_soldes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/soldes/index.html.twig');
    }
}
