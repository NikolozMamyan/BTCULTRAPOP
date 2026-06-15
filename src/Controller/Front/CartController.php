<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_front_cart', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/cart/index.html.twig');
    }
}
