<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\HomeProductSelection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_front_home', methods: ['GET'])]
    public function index(HomeProductSelection $homeProducts): Response
    {
        $user = $this->getUser();

        return $this->render('front/home/index.html.twig', [
            'home_products' => $homeProducts->products($user instanceof User ? $user : null),
        ]);
    }
}
