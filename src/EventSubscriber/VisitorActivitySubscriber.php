<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\VisitorActivityTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class VisitorActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VisitorActivityTracker $visitorActivityTracker,
        private TokenStorageInterface $tokenStorage,
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

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->visitorActivityTracker->track(
            $event->getRequest(),
            $event->getResponse(),
            $user instanceof User ? $user : null,
        );
    }
}
