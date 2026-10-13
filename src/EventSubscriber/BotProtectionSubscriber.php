<?php

namespace App\EventSubscriber;

use App\Service\BotTrafficGuard;
use App\Service\VisitorEventRecorder;
use App\Service\VisitorIpManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BotProtectionSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_PATHS = [
        '/stripe/webhook',
        '/merchant-center/products.xml',
        '/robots.txt',
        '/sitemap.xml',
        '/llms.txt',
        '/favicon.ico',
    ];
    private const STATIC_PREFIXES = ['/assets', '/build', '/uploads', '/img', '/styles', '/css', '/js', '/_profiler', '/_wdt'];

    public function __construct(
        private BotTrafficGuard $guard,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private VisitorIpManager $visitorIps,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 64]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isExcluded($event->getRequest())) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(VisitorEventRecorder::TRACK_ATTRIBUTE, true);

        if ($this->visitorIps->isBlocked($request->getClientIp())) {
            $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'blocked_request');
            $locale = $request->cookies->getString('ultrapop_locale', 'fr');
            $message = $this->translator->trans('bot_protection.ip_blocked', locale: in_array($locale, ['fr', 'en'], true) ? $locale : 'fr');
            $json = str_starts_with($request->getPathInfo(), '/api/')
                || $request->isXmlHttpRequest()
                || str_contains($request->headers->get('Accept', ''), 'application/json');
            $response = $json
                ? new JsonResponse(['error' => 'ip_blocked', 'message' => $message], Response::HTTP_FORBIDDEN)
                : new Response($message, Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain; charset=UTF-8']);
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $event->setResponse($response);

            return;
        }

        if ('/verification' === rtrim($request->getPathInfo(), '/')) {
            return;
        }

        $decision = $this->guard->inspect($request);

        if (0 === $decision['retry_after']) {
            return;
        }

        $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, $decision['challenge'] ? 'captcha_required' : 'rate_limited');

        $locale = $request->cookies->getString('ultrapop_locale', 'fr');
        $message = $this->translator->trans(
            $decision['challenge'] ? 'bot_protection.required' : 'bot_protection.too_many_requests',
            ['%seconds%' => $decision['retry_after']],
            locale: in_array($locale, ['fr', 'en'], true) ? $locale : 'fr',
        );
        $url = $this->urlGenerator->generate('app_bot_challenge', ['return_to' => $this->returnTarget($request)]);
        $json = str_starts_with($request->getPathInfo(), '/api/')
            || $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');

        if ($json) {
            $response = new JsonResponse([
                'error' => $decision['challenge'] ? 'bot_verification_required' : 'rate_limit_exceeded',
                'message' => $message,
                'verification_url' => $decision['challenge'] ? $url : null,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        } elseif ($decision['challenge']) {
            $response = new RedirectResponse($url, $request->isMethodCacheable() ? Response::HTTP_FOUND : Response::HTTP_SEE_OTHER);
        } else {
            $response = new Response($message, Response::HTTP_TOO_MANY_REQUESTS, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if ($decision['challenge']) {
            $response->headers->set('X-Bot-Challenge', $url);
        }

        $response->headers->set('Retry-After', (string) $decision['retry_after']);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $event->setResponse($response);
    }

    private function isExcluded(Request $request): bool
    {
        $path = rtrim($request->getPathInfo(), '/') ?: '/';

        if (in_array($path, self::EXCLUDED_PATHS, true)) {
            return true;
        }

        foreach (self::STATIC_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function returnTarget(Request $request): string
    {
        if ($request->isMethodCacheable() && !str_starts_with($request->getPathInfo(), '/api/')) {
            return $request->getRequestUri();
        }

        $origin = $request->getSchemeAndHttpHost();
        $referer = $request->headers->get('Referer', '');

        return str_starts_with($referer, $origin . '/') ? substr($referer, strlen($origin)) : '';
    }
}
