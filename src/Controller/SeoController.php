<?php

namespace App\Controller;

use App\Service\SeoIndexingPolicy;
use App\Service\StorefrontSeoCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_seo_robots', methods: ['GET'])]
    public function robots(Request $request, SeoIndexingPolicy $indexingPolicy): Response
    {
        return $this->textResponse($this->renderView('seo/robots.txt.twig', [
            'indexable' => $indexingPolicy->isIndexableHost($request->getHost()),
        ]));
    }

    #[Route('/sitemap.xml', name: 'app_seo_sitemap', methods: ['GET'])]
    public function sitemap(StorefrontSeoCatalog $seoCatalog): Response
    {
        $response = new Response($this->renderView('seo/sitemap.xml.twig', [
            'pages' => $seoCatalog->publicPages(),
            'products' => $seoCatalog->products(),
            'blog_posts' => $seoCatalog->blogPosts(),
        ]));
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/llms.txt', name: 'app_seo_llms', methods: ['GET'])]
    public function llms(StorefrontSeoCatalog $seoCatalog): Response
    {
        return $this->textResponse($this->renderView('seo/llms.txt.twig', [
            'products' => $seoCatalog->products(),
            'blog_posts' => $seoCatalog->blogPosts(),
        ]));
    }

    private function textResponse(string $content): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
