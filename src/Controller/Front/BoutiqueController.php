<?php

namespace App\Controller\Front;

use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoutiqueController extends AbstractController
{
    #[Route('/boutique', name: 'app_front_boutique', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $products = $catalog->all();

        return $this->render('front/boutique/index.html.twig', [
            'products' => $products,
            'categories' => $catalog->categoriesFor($products),
            'max_price' => $catalog->maxPriceFor($products),
        ]);
    }

    #[Route('/boutique/product/{id}', name: 'app_front_product', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function product(int $id, StorefrontProductCatalog $catalog): Response
    {
        $product = $catalog->findEntity($id);

        if (null === $product) {
            throw $this->createNotFoundException(sprintf('Product %d was not found.', $id));
        }

        return $this->render('front/boutique/show.html.twig', [
            'product' => $catalog->present($product),
            'related_products' => $catalog->related($product),
        ]);
    }
}
