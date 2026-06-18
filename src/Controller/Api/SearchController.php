<?php

namespace App\Controller\Api;

use App\Service\StorefrontSearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search')]
final class SearchController extends AbstractController
{
    #[Route('/products', name: 'app_api_search_products', methods: ['GET'])]
    public function products(Request $request, StorefrontSearchProvider $search): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([
                'query' => $query,
                'results' => [],
            ]);
        }

        return $this->json([
            'query' => $query,
            'results' => $search->search($query),
        ]);
    }
}
