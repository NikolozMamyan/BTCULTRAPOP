<?php

namespace App\Controller\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Service\CartManager;
use App\Service\CartResolver;
use App\Service\CartViewBuilder;
use App\Service\TemporaryCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/cart')]
final class CartController extends AbstractController
{
    #[Route('', name: 'app_api_cart_show', methods: ['GET'])]
    public function show(
        Request $request,
        CartResolver $cartResolver,
        CartViewBuilder $cartViewBuilder,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser());

        if ($cart instanceof Cart) {
            $entityManager->flush();
        }

        $response = $this->json(['cart' => $cartViewBuilder->build($cart)]);

        if ($cart instanceof Cart) {
            $response->headers->setCookie($cartResolver->createCookie($cart, $request));
        }

        return $response;
    }

    #[Route('/items', name: 'app_api_cart_item_add', methods: ['POST'])]
    public function addItem(
        Request $request,
        CartResolver $cartResolver,
        CartManager $cartManager,
        CartViewBuilder $cartViewBuilder,
        TemporaryCatalog $catalog,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->isValidCartCsrf($request)) {
            return $this->error('auth.flash.invalid_csrf', $translator, Response::HTTP_FORBIDDEN);
        }

        $payload = $this->jsonPayload($request);
        $productId = (int) ($payload['productId'] ?? 0);
        $quantity = max(1, min(99, (int) ($payload['quantity'] ?? 1)));
        $product = $catalog->ensureProduct($productId);

        if (null === $product) {
            return $this->error('cart.flash.product_not_found', $translator, Response::HTTP_NOT_FOUND);
        }

        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser(), true);
        \assert($cart instanceof Cart);

        $cartManager->addProduct($cart, $product, $quantity);
        $entityManager->flush();

        return $this->cartResponse(
            $request,
            $cart,
            $cartResolver,
            $cartViewBuilder,
            $translator->trans('cart.flash.added', ['%quantity%' => $quantity]),
        );
    }

    #[Route('/items/{id}', name: 'app_api_cart_item_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateItem(
        int $id,
        Request $request,
        CartResolver $cartResolver,
        CartManager $cartManager,
        CartViewBuilder $cartViewBuilder,
        CartItemRepository $cartItems,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->isValidCartCsrf($request)) {
            return $this->error('auth.flash.invalid_csrf', $translator, Response::HTTP_FORBIDDEN);
        }

        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser());
        $item = $cartItems->find($id);

        if (!$cart instanceof Cart || !$this->ownsItem($cart, $item)) {
            return $this->error('cart.flash.item_not_found', $translator, Response::HTTP_NOT_FOUND);
        }

        $payload = $this->jsonPayload($request);
        $quantity = (int) ($payload['quantity'] ?? 1);

        if ($quantity <= 0) {
            $cartManager->removeItem($cart, $item);
            $message = 'cart.flash.removed';
        } else {
            $cartManager->updateQuantity($item, min(99, $quantity));
            $message = 'cart.flash.updated';
        }

        $entityManager->flush();

        return $this->cartResponse(
            $request,
            $cart,
            $cartResolver,
            $cartViewBuilder,
            $translator->trans($message),
        );
    }

    #[Route('/items/{id}', name: 'app_api_cart_item_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function removeItem(
        int $id,
        Request $request,
        CartResolver $cartResolver,
        CartManager $cartManager,
        CartViewBuilder $cartViewBuilder,
        CartItemRepository $cartItems,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->isValidCartCsrf($request)) {
            return $this->error('auth.flash.invalid_csrf', $translator, Response::HTTP_FORBIDDEN);
        }

        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser());
        $item = $cartItems->find($id);

        if (!$cart instanceof Cart || !$this->ownsItem($cart, $item)) {
            return $this->error('cart.flash.item_not_found', $translator, Response::HTTP_NOT_FOUND);
        }

        $cartManager->removeItem($cart, $item);
        $entityManager->flush();

        return $this->cartResponse(
            $request,
            $cart,
            $cartResolver,
            $cartViewBuilder,
            $translator->trans('cart.flash.removed'),
        );
    }

    private function cartResponse(
        Request $request,
        Cart $cart,
        CartResolver $cartResolver,
        CartViewBuilder $cartViewBuilder,
        string $message,
    ): JsonResponse {
        $response = $this->json([
            'cart' => $cartViewBuilder->build($cart),
            'message' => $message,
        ]);
        $response->headers->setCookie($cartResolver->createCookie($cart, $request));

        return $response;
    }

    private function error(string $message, TranslatorInterface $translator, int $statusCode): JsonResponse
    {
        return $this->json([
            'message' => $translator->trans($message),
        ], $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $content = trim($request->getContent());

        if ('' === $content) {
            return [];
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function isValidCartCsrf(Request $request): bool
    {
        return $this->isCsrfTokenValid('cart_api', $request->headers->get('X-CSRF-Token', ''));
    }

    private function ownsItem(Cart $cart, ?CartItem $item): bool
    {
        return $item instanceof CartItem && $item->getCart()?->getId() === $cart->getId();
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
