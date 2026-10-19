<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class CanonicalHostSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(SEO_ORIGIN)%')]
        private string $seoOrigin,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 128]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'www.ultrapop.com' !== $event->getRequest()->getHost()) {
            return;
        }

        $request = $event->getRequest();
        $event->setResponse(new RedirectResponse(
            rtrim($this->seoOrigin, '/') . $request->getRequestUri(),
            $request->isMethodCacheable() ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_PERMANENTLY_REDIRECT,
        ));
    }
}
