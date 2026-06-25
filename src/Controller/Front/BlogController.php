<?php

namespace App\Controller\Front;

use App\Repository\BlogPostRepository;
use App\Service\BlogPostPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'app_front_blog', methods: ['GET'])]
    public function index(BlogPostRepository $posts, BlogPostPresenter $presenter): Response
    {
        return $this->render('front/blog/index.html.twig', [
            'posts' => $presenter->presentMany($posts->findPublished()),
        ]);
    }

    #[Route('/blog/{slug}', name: 'app_front_blog_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(
        string $slug,
        BlogPostRepository $posts,
        BlogPostPresenter $presenter,
    ): Response {
        $post = $posts->findPublishedBySlug($slug);

        if (null === $post) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/blog/show.html.twig', [
            'post' => $presenter->present($post, true),
            'related_posts' => $presenter->presentMany($posts->findRelated($post)),
        ]);
    }
}
