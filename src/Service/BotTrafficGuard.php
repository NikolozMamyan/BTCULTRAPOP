<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class BotTrafficGuard
{
    public const COOKIE_NAME = 'ultrapop_verified';
    private const SUSPICION_TTL = 900;
    private const VERIFICATION_TTL = 1800;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private RateLimiterFactory $botBurstLimiter,
        private RateLimiterFactory $botSustainedLimiter,
        private RateLimiterFactory $botVerifiedBurstLimiter,
        private RateLimiterFactory $botVerifiedSustainedLimiter,
        private RateLimiterFactory $botChallengeLimiter,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /** @return array{challenge: bool, retry_after: int} */
    public function inspect(Request $request): array
    {
        $key = $this->clientKey($request);
        $verified = $this->isVerified($request);
        $suspicion = $this->cache->getItem('bot_suspicion_' . $key);

        if (!$verified && $suspicion->isHit()) {
            return ['challenge' => true, 'retry_after' => 60];
        }

        $burst = $verified ? $this->botVerifiedBurstLimiter : $this->botBurstLimiter;
        $sustained = $verified ? $this->botVerifiedSustainedLimiter : $this->botSustainedLimiter;
        $retryAfter = $this->consume([$burst, $sustained], $key);

        if ($retryAfter > 0 && !$verified) {
            $suspicion->set(true)->expiresAfter(self::SUSPICION_TTL);
            $this->cache->save($suspicion);
        }

        return ['challenge' => $retryAfter > 0 && !$verified, 'retry_after' => $retryAfter];
    }

    public function consumeChallengeAttempt(Request $request): int
    {
        return $this->consume([$this->botChallengeLimiter], $this->clientKey($request));
    }

    public function verificationCookie(Request $request): Cookie
    {
        $expires = time() + self::VERIFICATION_TTL;

        return Cookie::create(self::COOKIE_NAME)
            ->withValue($expires . '.' . $this->signature($request, $expires))
            ->withExpires($expires)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function isVerified(Request $request): bool
    {
        $value = $request->cookies->getString(self::COOKIE_NAME);

        if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/D', $value, $matches)) {
            return false;
        }

        $expires = (int) $matches[1];

        return $expires > time()
            && $expires <= time() + self::VERIFICATION_TTL
            && hash_equals($this->signature($request, $expires), $matches[2]);
    }

    /** @param list<RateLimiterFactory> $limiters */
    private function consume(array $limiters, string $key): int
    {
        $retryAfter = 0;

        foreach ($limiters as $factory) {
            $limit = $factory->create($key)->consume();

            if (!$limit->isAccepted()) {
                $retryAfter = max($retryAfter, $limit->getRetryAfter()->getTimestamp() - time(), 1);
            }
        }

        return $retryAfter;
    }

    private function clientKey(Request $request): string
    {
        return hash_hmac('sha256', $request->getClientIp() ?? 'unknown', $this->secret);
    }

    private function signature(Request $request, int $expires): string
    {
        return hash_hmac('sha256', $expires . '|' . $this->clientKey($request) . '|' . $request->headers->get('User-Agent', ''), $this->secret);
    }
}
