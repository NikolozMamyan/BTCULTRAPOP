<?php

namespace App\Controller;

use App\Service\MerchantCenterFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MerchantCenterController extends AbstractController
{
    #[Route('/merchant-center/products.xml', name: 'app_merchant_center_products', methods: ['GET'])]
    public function products(MerchantCenterFeedBuilder $feedBuilder): Response
    {
        $response = new Response($feedBuilder->build(), Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
