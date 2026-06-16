<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class UserSessionManager
{
    public const COOKIE_NAME = 'ultrapop_auth';

    private const IDLE_TTL = '+30 days';
    private const ABSOLUTE_TTL = '+6 months';
    private const ROTATION_INTERVAL = '-15 minutes';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSessionRepository $userSessions,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function createSession(User $user, Request $request): Cookie
    {
        $now = new \DateTimeImmutable();
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $expiresAt = $now->modify(self::IDLE_TTL);

        $session = (new UserSession())
            ->setUser($user)
            ->setSelector($selector)
            ->setTokenHash($this->hashToken($token))
            ->setDeviceName($this->detectDeviceName($request))
            ->setUserAgentHash($this->hashUserAgent($request))
            ->setIpAddress($request->getClientIp())
            ->setLastSeenAt($now)
            ->setExpiresAt($expiresAt)
            ->setAbsoluteExpiresAt($now->modify(self::ABSOLUTE_TTL));

        $user->setLastLoginAt($now);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $this->createCookie($request, $selector, $token, $expiresAt);
    }

    public function authenticateRequest(Request $request): ?AuthenticatedUserSession
    {
        $cookieValue = $request->cookies->getString(self::COOKIE_NAME);
        [$selector, $token] = $this->splitCookieValue($cookieValue);

        if (null === $selector || null === $token) {
            return null;
        }

        $session = $this->userSessions->findOneBy(['selector' => $selector]);
        $now = new \DateTimeImmutable();

        if (!$session instanceof UserSession) {
            return null;
        }

        if ($session->isRevoked() || $session->isExpired($now) || !$session->getUser()?->isActive()) {
            $session->revoke($now);
            $this->entityManager->flush();

            return null;
        }

        if (!hash_equals($session->getTokenHash(), $this->hashToken($token))) {
            $session->revoke($now);
            $this->entityManager->flush();

            return null;
        }

        return new AuthenticatedUserSession($session, $token);
    }

    public function refreshSession(AuthenticatedUserSession $authenticatedSession, Request $request): Cookie
    {
        $session = $authenticatedSession->session;
        $token = $authenticatedSession->token;
        $now = new \DateTimeImmutable();
        $shouldRotate = $session->getLastSeenAt() <= $now->modify(self::ROTATION_INTERVAL);

        if ($shouldRotate) {
            $token = bin2hex(random_bytes(32));
            $session->setTokenHash($this->hashToken($token));
        }

        $expiresAt = min($now->modify(self::IDLE_TTL), $session->getAbsoluteExpiresAt());

        $session
            ->setLastSeenAt($now)
            ->setExpiresAt($expiresAt)
            ->setIpAddress($request->getClientIp())
            ->setUserAgentHash($this->hashUserAgent($request));

        $this->entityManager->flush();

        return $this->createCookie($request, $session->getSelector(), $token, $expiresAt);
    }

    public function revokeCurrentSession(Request $request): Cookie
    {
        $authenticatedSession = $this->authenticateRequest($request);

        if (null !== $authenticatedSession) {
            $authenticatedSession->session->revoke();
            $this->entityManager->flush();
        }

        return $this->createExpiredCookie($request);
    }

    public function createExpiredCookie(Request $request): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    private function createCookie(Request $request, string $selector, string $token, \DateTimeImmutable $expiresAt): Cookie
    {
        return Cookie::create(
            self::COOKIE_NAME,
            sprintf('%s:%s', $selector, $token),
            $expiresAt,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitCookieValue(string $cookieValue): array
    {
        if (!str_contains($cookieValue, ':')) {
            return [null, null];
        }

        [$selector, $token] = explode(':', $cookieValue, 2);

        if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return [null, null];
        }

        return [$selector, $token];
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, $this->secret);
    }

    private function hashUserAgent(Request $request): ?string
    {
        $userAgent = $request->headers->get('User-Agent');

        return null === $userAgent ? null : hash('sha256', $userAgent);
    }

    private function detectDeviceName(Request $request): string
    {
        $userAgent = mb_strtolower($request->headers->get('User-Agent', ''));

        if (str_contains($userAgent, 'ipad') || str_contains($userAgent, 'tablet')) {
            return 'Tablette';
        }

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'android')) {
            return 'Mobile';
        }

        return 'Ordinateur';
    }
}
