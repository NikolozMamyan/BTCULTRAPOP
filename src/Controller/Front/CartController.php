<?php

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\User;
use App\Form\CheckoutAddressType;
use App\Model\CheckoutAddress;
use App\Service\CartResolver;
use App\Service\CartViewBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        $cart = $cartResolver->resolve($request, $this->getAuthenticatedUser());

        if ($cart instanceof Cart) {
            $entityManager->flush();
        }

        $response = $this->render('front/cart/index.html.twig', [
            'cart' => $cartViewBuilder->build($cart),
            'checkout_form' => $this->createForm(CheckoutAddressType::class, CheckoutAddress::fromUser($this->getAuthenticatedUser()), [
                'action' => $this->generateUrl('app_checkout_stripe_create'),
            ])->createView(),
        ]);

        if ($cart instanceof Cart) {
            $response->headers->setCookie($cartResolver->createCookie($cart, $request));
        }

        return $response;
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
