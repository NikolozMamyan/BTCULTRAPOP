<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserFavoriteRepository;
use App\Service\UserFavoriteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/favorites')]
final class FavoriteController extends AbstractController
{
    #[Route('/{productId}', name: 'app_api_favorite_toggle', requirements: ['productId' => '\d+'], methods: ['POST'])]
    public function toggle(
        int $productId,
        Request $request,
        ProductRepository $products,
        UserFavoriteRepository $favorites,
        UserFavoriteManager $favoriteManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('favorite_api', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->error('auth.flash.invalid_csrf', $translator, Response::HTTP_FORBIDDEN);
        }

        $user = $this->getAuthenticatedUser();

        if (!$user instanceof User) {
            return $this->error('favorite.flash.login_required', $translator, Response::HTTP_UNAUTHORIZED);
        }

        $product = $products->find($productId);

        if (null === $product) {
            return $this->error('cart.flash.product_not_found', $translator, Response::HTTP_NOT_FOUND);
        }

        $favorite = $favoriteManager->toggle($user, $product);

        return $this->json([
            'favorite' => $favorite,
            'count' => $favorites->countForUser($user),
            'message' => $translator->trans($favorite ? 'favorite.flash.added' : 'favorite.flash.removed'),
        ]);
    }

    private function error(string $message, TranslatorInterface $translator, int $statusCode): JsonResponse
    {
        return $this->json([
            'message' => $translator->trans($message),
        ], $statusCode);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
