<?php

namespace App\Controller\Front;

use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LicencesController extends AbstractController
{
    #[Route('/licences', name: 'app_front_licences', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $products = $catalog->all();

        return $this->render('front/licences/index.html.twig', [
            'products' => $products,
            'categories' => $catalog->categoriesFor($products),
            'max_price' => $catalog->maxPriceFor($products),
        ]);
    }
}
