<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\VisitorActivityTracker;
use App\Service\VisitorEventRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class VisitorActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VisitorActivityTracker $visitorActivityTracker,
        private TokenStorageInterface $tokenStorage,
        private VisitorEventRecorder $events,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -64],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->events->record($event->getRequest(), $event->getResponse());

        if (Response::HTTP_TOO_MANY_REQUESTS === $event->getResponse()->getStatusCode()
            || $event->getResponse()->headers->has('X-Bot-Challenge')
            || 'app_bot_challenge' === $event->getRequest()->attributes->get('_route')
            || 'blocked_request' === $event->getRequest()->attributes->get(VisitorEventRecorder::TYPE_ATTRIBUTE)
        ) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->visitorActivityTracker->track(
            $event->getRequest(),
            $event->getResponse(),
            $user instanceof User ? $user : null,
        );
    }
}
