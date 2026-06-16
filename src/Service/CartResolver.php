<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\User;
use App\Enum\CartStatus;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final readonly class CartResolver
{
    public const COOKIE_NAME = 'ultrapop_cart';

    public function __construct(
        private CartRepository $carts,
        private CartManager $cartManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(Request $request, ?User $user = null, bool $create = false): ?Cart
    {
        $tokenCart = $this->resolveTokenCart($request);

        if ($user instanceof User) {
            $userCart = $this->resolveUserCart($user);

            if ($userCart instanceof Cart) {
                if ($this->canUseTokenCartForUser($tokenCart, $user) && $tokenCart !== $userCart) {
                    $this->cartManager->merge($tokenCart, $userCart);
                }

                return $userCart;
            }

            if ($this->canUseTokenCartForUser($tokenCart, $user)) {
                $tokenCart->setUser($user);

                return $tokenCart;
            }

            return $create ? $this->createCart($user) : null;
        }

        if ($tokenCart instanceof Cart && null === $tokenCart->getUser()) {
            return $tokenCart;
        }

        return $create ? $this->createCart() : null;
    }

    private function createCart(?User $user = null): Cart
    {
        $cart = $this->cartManager->createCart($user);
        $this->entityManager->persist($cart);

        return $cart;
    }

    private function resolveTokenCart(Request $request): ?Cart
    {
        $token = $request->cookies->getString(self::COOKIE_NAME);

        if ('' === $token) {
            return null;
        }

        return $this->carts->findOneBy([
            'token' => $token,
            'status' => CartStatus::ACTIVE,
        ]);
    }

    private function resolveUserCart(User $user): ?Cart
    {
        return $this->carts->findOneBy([
            'user' => $user,
            'status' => CartStatus::ACTIVE,
        ], [
            'updatedAt' => 'DESC',
            'id' => 'DESC',
        ]);
    }

    private function canUseTokenCartForUser(?Cart $cart, User $user): bool
    {
        if (!$cart instanceof Cart) {
            return false;
        }

        $cartUser = $cart->getUser();

        return null === $cartUser || $cartUser->getId() === $user->getId();
    }

    public function createCookie(Cart $cart, Request $request): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($cart->getToken() ?? '')
            ->withExpires($cart->getExpiresAt() ?? new \DateTimeImmutable('+30 days'))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
