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
        $token = $request->cookies->getString(self::COOKIE_NAME);
        $cart = '' === $token ? null : $this->carts->findOneBy([
            'token' => $token,
            'status' => CartStatus::ACTIVE,
        ]);

        if ($cart instanceof Cart) {
            if ($user instanceof User && null === $cart->getUser()) {
                $cart->setUser($user);
            }

            return $cart;
        }

        if ($user instanceof User) {
            $cart = $this->carts->findOneBy([
                'user' => $user,
                'status' => CartStatus::ACTIVE,
            ]);

            if ($cart instanceof Cart) {
                return $cart;
            }
        }

        if (!$create) {
            return null;
        }

        $cart = $this->cartManager->createCart($user);
        $this->entityManager->persist($cart);

        return $cart;
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
