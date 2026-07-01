<?php

namespace App\Controller\Media;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ThreeDProductMediaController extends AbstractController
{
    #[Route(
        '/media/3dproduct/{filename}',
        name: 'app_media_3d_product_texture',
        requirements: ['filename' => '[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)'],
        methods: ['GET'],
    )]
    public function texture(
        string $filename,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ): Response {
        $path = $projectDir . '/assets/img/3dproduct/' . basename($filename);

        if (!is_file($path)) {
            throw $this->createNotFoundException('3D product texture not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', mime_content_type($path) ?: 'application/octet-stream');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->setPublic();
        $response->setMaxAge(604800);

        return $response;
    }
}
