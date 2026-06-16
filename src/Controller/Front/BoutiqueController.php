<?php

namespace App\Controller\Front;

use App\Service\TemporaryCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BoutiqueController extends AbstractController
{
    #[Route('/boutique', name: 'app_front_boutique', methods: ['GET'])]
    public function index(TemporaryCatalog $catalog): Response
    {
        return $this->render('front/boutique/index.html.twig', [
            'products' => $catalog->all(),
        ]);
    }

    #[Route('/boutique/product/{id}', name: 'app_front_product', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function product(int $id, TemporaryCatalog $catalog): Response
    {
        $product = $catalog->find($id);

        if (null === $product) {
            throw $this->createNotFoundException(sprintf('Product %d was not found.', $id));
        }

        $relatedProducts = array_values(array_filter(
            $catalog->all(),
            static fn (array $candidate): bool => $candidate['id'] !== $id,
        ));

        return $this->render('front/boutique/show.html.twig', [
            'product' => $product,
            'related_products' => array_slice($relatedProducts, 0, 3),
        ]);
    }
}
