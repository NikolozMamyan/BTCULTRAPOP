<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class VisitorActivityTracker
{
    public const COOKIE_NAME = 'ultrapop_visitor';

    public function __construct(private Connection $connection)
    {
    }

    public function track(Request $request, Response $response, ?User $user): void
    {
        if (!$this->shouldTrack($request)) {
            return;
        }

        try {
            $now = new \DateTimeImmutable();
            $token = $this->visitorToken($request);
            $cartId = $this->cartId($request, $response);
            $userId = $user?->getId();
            $visitorType = $user instanceof User ? 'customer' : 'guest';
            $expiresAt = $now->modify('+30 days');

            $this->connection->executeStatement(
                'INSERT INTO site_visitor (
                    visitor_token,
                    user_id,
                    cart_id,
                    visitor_type,
                    ip_hash,
                    user_agent_hash,
                    device_name,
                    current_path,
                    current_route,
                    referer,
                    first_seen_at,
                    last_seen_at,
                    expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    cart_id = COALESCE(VALUES(cart_id), cart_id),
                    visitor_type = VALUES(visitor_type),
                    ip_hash = VALUES(ip_hash),
                    user_agent_hash = VALUES(user_agent_hash),
                    device_name = VALUES(device_name),
                    current_path = VALUES(current_path),
                    current_route = VALUES(current_route),
                    referer = VALUES(referer),
                    last_seen_at = VALUES(last_seen_at),
                    expires_at = VALUES(expires_at)',
                [
                    $token,
                    $userId,
                    $cartId,
                    $visitorType,
                    $this->hashNullable($request->getClientIp()),
                    $this->hashNullable($request->headers->get('User-Agent')),
                    $this->deviceName($request->headers->get('User-Agent', '')),
                    $this->truncate($request->getRequestUri(), 255),
                    $this->truncate((string) $request->attributes->get('_route', ''), 120),
                    $this->truncate((string) $request->headers->get('referer', ''), 255),
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                    $expiresAt->format('Y-m-d H:i:s'),
                ],
            );

            $response->headers->setCookie(
                Cookie::create(self::COOKIE_NAME)
                    ->withValue($token)
                    ->withExpires($expiresAt)
                    ->withPath('/')
                    ->withSecure($request->isSecure())
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        } catch (\Throwable) {
            // Presence tracking must never break storefront browsing if the migration
            // has not been deployed yet or if the tracking table is temporarily unavailable.
        }
    }

    private function shouldTrack(Request $request): bool
    {
        if (!$request->isMethodCacheable() && !str_starts_with((string) $request->attributes->get('_route', ''), 'app_api_cart')) {
            return false;
        }

        $route = (string) $request->attributes->get('_route', '');

        if ('' === $route || str_starts_with($route, '_') || str_starts_with($route, 'app_admin')) {
            return false;
        }

        if (str_starts_with($request->getPathInfo(), '/assets/')) {
            return false;
        }

        return !str_starts_with($route, 'app_stripe_webhook');
    }

    private function visitorToken(Request $request): string
    {
        $token = $request->cookies->getString(self::COOKIE_NAME);

        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $token;
        }

        return bin2hex(random_bytes(32));
    }

    private function cartId(Request $request, Response $response): ?int
    {
        $cartToken = $this->cartTokenFromResponse($response) ?? $request->cookies->getString(CartResolver::COOKIE_NAME);

        if ('' === $cartToken) {
            return null;
        }

        $cartId = $this->connection->fetchOne(
            'SELECT id FROM cart WHERE token = ? ORDER BY updated_at DESC, id DESC LIMIT 1',
            [$cartToken],
        );

        return false === $cartId || null === $cartId ? null : (int) $cartId;
    }

    private function cartTokenFromResponse(Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (CartResolver::COOKIE_NAME === $cookie->getName()) {
                $value = trim($cookie->getValue() ?? '');

                return '' === $value ? null : $value;
            }
        }

        return null;
    }

    private function hashNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : hash('sha256', $value);
    }

    private function deviceName(string $userAgent): string
    {
        $userAgent = mb_strtolower($userAgent);

        $device = str_contains($userAgent, 'mobile') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'android')
            ? 'Mobile'
            : 'Desktop';

        $browser = match (true) {
            str_contains($userAgent, 'edg/') => 'Edge',
            str_contains($userAgent, 'chrome/') => 'Chrome',
            str_contains($userAgent, 'firefox/') => 'Firefox',
            str_contains($userAgent, 'safari/') => 'Safari',
            default => 'Navigateur',
        };

        return sprintf('%s · %s', $device, $browser);
    }

    private function truncate(string $value, int $length): string
    {
        $value = trim($value);

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1) . '…';
    }
}
