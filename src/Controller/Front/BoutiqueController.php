<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoutiqueController extends AbstractController
{
    private const IMAGES = [
        'https://ultrapop.com/img/p/1/7/0/170.jpg',
        'https://ultrapop.com/img/p/1/6/7/167.jpg',
        'https://ultrapop.com/img/p/1/4/5/145.jpg',
        'https://ultrapop.com/img/p/1/6/1/161.jpg',
    ];

    private const PRODUCTS = [
        ['id' => 1, 'name' => 'Figurine Collector Arcane', 'cat' => 'Figurines', 'price' => 59.90, 'old' => 79.90, 'img' => self::IMAGES[0], 'rating' => 4.8, 'pop' => 98, 'tag' => 'Promo'],
        ['id' => 2, 'name' => 'Statuette Premium One Piece', 'cat' => 'Figurines', 'price' => 89.90, 'old' => null, 'img' => self::IMAGES[1], 'rating' => 4.9, 'pop' => 95, 'tag' => 'Nouveau'],
        ['id' => 3, 'name' => 'Manga Édition Deluxe', 'cat' => 'Mangas', 'price' => 24.90, 'old' => 29.90, 'img' => self::IMAGES[2], 'rating' => 4.6, 'pop' => 80, 'tag' => 'Promo'],
        ['id' => 4, 'name' => 'Pack Goodies Collector', 'cat' => 'Goodies', 'price' => 34.90, 'old' => null, 'img' => self::IMAGES[3], 'rating' => 4.5, 'pop' => 72, 'tag' => null],
        ['id' => 5, 'name' => 'Figurine Édition Limitée', 'cat' => 'Figurines', 'price' => 74.90, 'old' => 99.90, 'img' => self::IMAGES[1], 'rating' => 4.7, 'pop' => 90, 'tag' => 'Promo'],
        ['id' => 6, 'name' => 'Coffret Manga Intégrale', 'cat' => 'Mangas', 'price' => 109.90, 'old' => null, 'img' => self::IMAGES[2], 'rating' => 4.9, 'pop' => 88, 'tag' => 'Nouveau'],
        ['id' => 7, 'name' => 'Mug Céramique Premium', 'cat' => 'Goodies', 'price' => 19.90, 'old' => 24.90, 'img' => self::IMAGES[3], 'rating' => 4.3, 'pop' => 60, 'tag' => 'Promo'],
        ['id' => 8, 'name' => 'Figurine Mini Collector', 'cat' => 'Figurines', 'price' => 39.90, 'old' => null, 'img' => self::IMAGES[0], 'rating' => 4.4, 'pop' => 68, 'tag' => 'Nouveau'],
    ];

    #[Route('/boutique', name: 'app_front_boutique', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/boutique/index.html.twig', [
            'products' => self::PRODUCTS,
        ]);
    }

    #[Route('/boutique/product/{id}', name: 'app_front_product', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function product(int $id): Response
    {
        $product = null;

        foreach (self::PRODUCTS as $candidate) {
            if ($candidate['id'] === $id) {
                $product = $candidate;
                break;
            }
        }

        if (null === $product) {
            throw $this->createNotFoundException(sprintf('Product %d was not found.', $id));
        }

        $relatedProducts = array_values(array_filter(
            self::PRODUCTS,
            static fn (array $candidate): bool => $candidate['id'] !== $id,
        ));

        return $this->render('front/boutique/show.html.twig', [
            'product' => $product,
            'related_products' => array_slice($relatedProducts, 0, 3),
        ]);
    }
}
