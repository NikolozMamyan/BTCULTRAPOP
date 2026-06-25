<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\ProductIngredientPresenter;
use App\Service\ProductSlugger;
use App\Service\StorefrontProductCatalog;
use App\Service\StorefrontProductReviews;
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

    #[Route('/boutique/product/{id}', name: 'app_front_product_legacy', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function legacyProduct(
        int $id,
        StorefrontProductCatalog $catalog,
        ProductSlugger $productSlugger,
    ): Response {
        $product = $catalog->findEntity($id);

        if (null === $product) {
            return $this->render('front/boutique/not_found.html.twig', [], new Response('', Response::HTTP_NOT_FOUND));
        }

        return $this->redirectToRoute(
            'app_front_product',
            $productSlugger->routeParameters($product),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    #[Route(
        '/boutique/produit/{id}-{slug}',
        name: 'app_front_product',
        requirements: ['id' => '\d+', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'],
        methods: ['GET'],
    )]
    public function product(
        int $id,
        string $slug,
        StorefrontProductCatalog $catalog,
        StorefrontProductReviews $productReviews,
        ProductIngredientPresenter $ingredientPresenter,
        ProductSlugger $productSlugger,
    ): Response
    {
        $product = $catalog->findEntity($id);

        if (null === $product) {
            return $this->render('front/boutique/not_found.html.twig', [], new Response('', Response::HTTP_NOT_FOUND));
        }

        if ($slug !== $productSlugger->slug($product)) {
            return $this->redirectToRoute(
                'app_front_product',
                $productSlugger->routeParameters($product),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        $reviews = $productReviews->forProduct($product);
        $presentedProduct = $catalog->presentForUser($product, $this->getAuthenticatedUser());
        $presentedProduct['ingredient_details'] = $ingredientPresenter->present($product->getIngredients());
        $presentedProduct['rating'] = $reviews['average'];
        $presentedProduct['review_count'] = $reviews['count'];

        return $this->render('front/boutique/show.html.twig', [
            'product' => $presentedProduct,
            'reviews' => $reviews,
            'related_products' => $catalog->related($product, user: $this->getAuthenticatedUser()),
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
