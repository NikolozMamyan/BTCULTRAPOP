<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\StorefrontProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LicencesController extends AbstractController
{
    #[Route('/licences', name: 'app_front_licences', methods: ['GET'])]
    public function index(StorefrontProductCatalog $catalog): Response
    {
        $products = $catalog->all($this->getAuthenticatedUser());

        return $this->render('front/licences/index.html.twig', [
            'products' => $products,
            'licenses' => $catalog->licensesFor($products),
            'max_price' => $catalog->maxPriceFor($products),
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
