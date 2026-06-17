<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoutiqueController extends AbstractController
{
    #[Route('/boutique', name: 'app_front_boutique', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $products = $catalog->all($this->getAuthenticatedUser());

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
            'product' => $catalog->presentForUser($product, $this->getAuthenticatedUser()),
            'related_products' => $catalog->related($product, user: $this->getAuthenticatedUser()),
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
