<?php

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\User;
use App\Form\CheckoutAddressType;
use App\Model\CheckoutAddress;
use App\Repository\PromoCodeRepository;
use App\Service\CartResolver;
use App\Service\CartViewBuilder;
use App\Service\PromoCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_front_cart', methods: ['GET'])]
    public function index(
        Request $request,
        CartResolver $cartResolver,
        CartViewBuilder $cartViewBuilder,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $user = $this->getAuthenticatedUser();
        $cart = $cartResolver->resolve($request, $user);
        $checkoutAddress = CheckoutAddress::fromUser($user);
        $hasSavedAddress = null !== $user?->getDefaultAddress();

        if ($cart instanceof Cart) {
            $entityManager->flush();
        }

        $response = $this->render('front/cart/index.html.twig', [
            'cart' => $cartViewBuilder->build($cart),
            'checkout_form' => $this->createForm(CheckoutAddressType::class, $checkoutAddress, [
                'action' => $this->generateUrl('app_checkout_stripe_create'),
            ])->createView(),
            'checkout_address' => $hasSavedAddress ? $checkoutAddress : null,
            'checkout_address_saved' => $hasSavedAddress,
        ]);

        if ($cart instanceof Cart) {
            $response->headers->setCookie($cartResolver->createCookie($cart, $request));
        }

        return $response;
    }

    #[Route('/cart/code-promo', name: 'app_front_cart_promo_apply', methods: ['POST'])]
    public function applyPromoCode(
        Request $request,
        CartResolver $cartResolver,
        PromoCodeRepository $promoCodes,
        PromoCodeManager $promoCodeManager,
        CartViewBuilder $cartViewBuilder,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_promo', $request->request->getString('_csrf_token'))) {
            return $this->promoResponse(
                $request,
                null,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                'promo.flash.invalid_csrf',
                false,
                Response::HTTP_FORBIDDEN,
            );
        }

        $user = $this->getAuthenticatedUser();
        $cart = $cartResolver->resolve($request, $user);

        if (!$cart instanceof Cart || 0 === $cart->getItems()->count()) {
            return $this->promoResponse(
                $request,
                $cart,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                'promo.flash.empty_cart',
                false,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $promoCode = $promoCodes->findOneByCode($request->request->getString('promo_code'));

        if (null === $promoCode) {
            return $this->promoResponse(
                $request,
                $cart,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                'promo.flash.not_found',
                false,
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $promoCodeManager->apply($cart, $promoCode, $user);
            $entityManager->flush();

            return $this->promoResponse(
                $request,
                $cart,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                'promo.flash.applied',
                true,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->promoResponse(
                $request,
                $cart,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                $exception->getMessage(),
                false,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    #[Route('/cart/code-promo/remove', name: 'app_front_cart_promo_remove', methods: ['POST'])]
    public function removePromoCode(
        Request $request,
        CartResolver $cartResolver,
        PromoCodeManager $promoCodeManager,
        CartViewBuilder $cartViewBuilder,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_promo', $request->request->getString('_csrf_token'))) {
            return $this->promoResponse(
                $request,
                null,
                $cartResolver,
                $cartViewBuilder,
                $translator,
                'promo.flash.invalid_csrf',
                false,
                Response::HTTP_FORBIDDEN,
            );
        }

        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser());

        if ($cart instanceof Cart) {
            $promoCodeManager->remove($cart);
            $entityManager->flush();
        }

        return $this->promoResponse(
            $request,
            $cart,
            $cartResolver,
            $cartViewBuilder,
            $translator,
            'promo.flash.removed',
            true,
        );
    }

    private function promoResponse(
        Request $request,
        ?Cart $cart,
        CartResolver $cartResolver,
        CartViewBuilder $cartViewBuilder,
        TranslatorInterface $translator,
        string $message,
        bool $success,
        int $statusCode = Response::HTTP_OK,
    ): Response {
        if ($this->wantsJson($request)) {
            $payload = [
                'message' => $translator->trans($message),
            ];

            if ($cart instanceof Cart) {
                $payload['cart'] = $cartViewBuilder->build($cart);
            }

            $response = new JsonResponse($payload, $statusCode);

            if ($cart instanceof Cart) {
                $response->headers->setCookie($cartResolver->createCookie($cart, $request));
            }

            return $response;
        }

        $this->addFlash($success ? 'promo_success' : 'promo_error', $message);

        return $this->redirectToRoute('app_front_cart');
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
