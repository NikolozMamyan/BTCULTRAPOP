<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class UserSessionAuthenticator extends AbstractAuthenticator
{
    public const REQUEST_ATTRIBUTE = '_ultrapop_authenticated_user_session';
    public const RESPONSE_COOKIE_ATTRIBUTE = '_ultrapop_auth_cookie';

    public function __construct(
        private readonly UserSessionManager $userSessionManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has(UserSessionManager::COOKIE_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $authenticatedSession = $this->userSessionManager->authenticateRequest($request);

        if (null === $authenticatedSession || null === $authenticatedSession->session->getUser()) {
            throw new AuthenticationException('Invalid ULTRAPOP authentication cookie.');
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $authenticatedSession);
        $user = $authenticatedSession->session->getUser();

        return new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn () => $user,
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $authenticatedSession = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($authenticatedSession instanceof AuthenticatedUserSession) {
            $request->attributes->set(
                self::RESPONSE_COOKIE_ATTRIBUTE,
                $this->userSessionManager->refreshSession($authenticatedSession, $request),
            );
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set(
            self::RESPONSE_COOKIE_ATTRIBUTE,
            $this->userSessionManager->createExpiredCookie($request),
        );

        return null;
    }
}
