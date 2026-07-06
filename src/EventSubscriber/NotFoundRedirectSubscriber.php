<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class NotFoundRedirectSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_PATH_PREFIXES = [
        '/admin',
        '/api',
        '/assets',
        '/build',
        '/uploads',
        '/_profiler',
        '/_wdt',
        '/stripe/webhook',
    ];

    private const EXCLUDED_EXACT_PATHS = [
        '/boutique',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/llms.txt',
    ];

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -8],
            KernelEvents::RESPONSE => ['onKernelResponse', -8],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        if (!$this->shouldRedirect($event->getRequest())) {
            return;
        }

        $event->setResponse($this->redirectToShop());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || Response::HTTP_NOT_FOUND !== $event->getResponse()->getStatusCode()) {
            return;
        }

        if (!$this->shouldRedirect($event->getRequest())) {
            return;
        }

        $event->setResponse($this->redirectToShop());
    }

    private function shouldRedirect(Request $request): bool
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = rtrim($request->getPathInfo(), '/') ?: '/';

        if (in_array($path, self::EXCLUDED_EXACT_PATHS, true)) {
            return false;
        }

        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        $accept = strtolower($request->headers->get('Accept', ''));

        return '' === $accept
            || str_contains($accept, 'text/html')
            || str_contains($accept, '*/*');
    }

    private function redirectToShop(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_front_boutique'),
            Response::HTTP_FOUND,
        );
    }
}
