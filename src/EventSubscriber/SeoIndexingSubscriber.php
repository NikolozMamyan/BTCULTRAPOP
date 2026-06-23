<?php

namespace App\EventSubscriber;

use App\Service\SeoIndexingPolicy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SeoIndexingSubscriber implements EventSubscriberInterface
{
    private const PRIVATE_ROUTE_PREFIXES = [
        'app_admin_',
        'app_api_',
        'app_auth_',
        'app_checkout_',
        'app_front_cart',
        'app_front_favoris',
        'app_front_profil',
        'app_front_profile_',
    ];

    public function __construct(private SeoIndexingPolicy $indexingPolicy)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if (
            !$this->indexingPolicy->isIndexableHost($request->getHost())
            || $this->isPrivateRoute($route)
        ) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    private function isPrivateRoute(string $route): bool
    {
        foreach (self::PRIVATE_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
