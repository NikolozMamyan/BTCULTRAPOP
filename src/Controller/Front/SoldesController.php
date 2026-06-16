<?php

namespace App\Controller\Front;

use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SoldesController extends AbstractController
{
    #[Route('/soldes', name: 'app_front_soldes', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $products = $catalog->onSale();

        return $this->render('front/soldes/index.html.twig', [
            'products' => $products,
            'categories' => $catalog->categoriesFor($products),
            'max_price' => $catalog->maxPriceFor($products),
        ]);
    }
}
