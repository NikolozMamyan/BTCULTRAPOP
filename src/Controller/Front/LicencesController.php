<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LicencesController extends AbstractController
{
    #[Route('/licences', name: 'app_front_licences', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/licences/index.html.twig');
    }
}
