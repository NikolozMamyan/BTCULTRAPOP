<?php

namespace App\EventSubscriber;

use App\Security\UserSessionAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'setAuthenticationCookie',
        ];
    }

    public function setAuthenticationCookie(ResponseEvent $event): void
    {
        $cookie = $event->getRequest()->attributes->get(UserSessionAuthenticator::RESPONSE_COOKIE_ATTRIBUTE);

        if (!$cookie instanceof Cookie) {
            return;
        }

        $event->getResponse()->headers->setCookie($cookie);
    }
}
