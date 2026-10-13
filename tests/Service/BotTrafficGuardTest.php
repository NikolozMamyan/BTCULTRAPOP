<?php

namespace App\Tests\Service;

use App\Service\BotTrafficGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class BotTrafficGuardTest extends TestCase
{
    public function testBurstRequiresVerificationAndAnotherIpIsUnaffected(): void
    {
        $guard = $this->guard();
        $request = $this->request();

        self::assertSame(0, $guard->inspect($request)['retry_after']);
        self::assertSame(0, $guard->inspect($request)['retry_after']);
        self::assertTrue($guard->inspect($request)['challenge']);
        self::assertTrue($guard->inspect($this->request(browser: 'another browser'))['challenge']);
        self::assertSame(0, $guard->inspect($this->request('192.0.2.2'))['retry_after']);
    }

    public function testSustainedTrafficTriggersVerificationWithoutExceedingTheBurstLimit(): void
    {
        $guard = $this->guard(burst: 20, sustained: 3);
        $request = $this->request();

        for ($index = 0; $index < 3; ++$index) {
            self::assertSame(0, $guard->inspect($request)['retry_after']);
        }

        self::assertTrue($guard->inspect($request)['challenge']);
    }

    public function testChangingAnUntrustedForwardedHeaderDoesNotBypassTheLimit(): void
    {
        $guard = $this->guard();

        for ($index = 0; $index < 3; ++$index) {
            $request = $this->request();
            $request->headers->set('X-Forwarded-For', '203.0.113.' . $index);
            $decision = $guard->inspect($request);
        }

        self::assertTrue($decision['challenge']);
    }

    public function testClearanceIsSignedAndBoundToTheIpAndBrowser(): void
    {
        $guard = $this->guard();
        $cookie = $guard->verificationCookie($this->request('2001:db8::1'));
        $request = $this->request('2001:db8::1');
        $request->cookies->set(BotTrafficGuard::COOKIE_NAME, $cookie->getValue());

        self::assertTrue($guard->isVerified($request));
        self::assertTrue($cookie->isHttpOnly());

        $otherIp = $this->request('2001:db8::2');
        $otherIp->cookies->set(BotTrafficGuard::COOKIE_NAME, $cookie->getValue());
        self::assertFalse($guard->isVerified($otherIp));

        $request->headers->set('User-Agent', 'another browser');
        self::assertFalse($guard->isVerified($request));

        $request->headers->set('User-Agent', 'test-browser');
        $request->cookies->set(BotTrafficGuard::COOKIE_NAME, time() + 100 . '.' . str_repeat('0', 64));
        self::assertFalse($guard->isVerified($request));
    }

    public function testExpiredClearanceIsRejectedEvenWithAValidSignature(): void
    {
        $guard = $this->guard();
        $request = $this->request();
        $expires = time() - 1;
        $key = hash_hmac('sha256', '192.0.2.1', 'test-secret');
        $signature = hash_hmac('sha256', $expires . '|' . $key . '|test-browser', 'test-secret');
        $request->cookies->set(BotTrafficGuard::COOKIE_NAME, $expires . '.' . $signature);

        self::assertFalse($guard->isVerified($request));
    }

    public function testVerifiedBrowsersCanPassSuspicionButStillHaveAHardLimit(): void
    {
        $guard = $this->guard();
        $request = $this->request();

        for ($index = 0; $index < 3; ++$index) {
            $guard->inspect($request);
        }

        $request->cookies->set(BotTrafficGuard::COOKIE_NAME, $guard->verificationCookie($request)->getValue());

        for ($index = 0; $index < 3; ++$index) {
            self::assertSame(0, $guard->inspect($request)['retry_after']);
        }

        $decision = $guard->inspect($request);
        self::assertFalse($decision['challenge']);
        self::assertGreaterThan(0, $decision['retry_after']);
    }

    public function testChallengeAttemptsAreLimitedSeparately(): void
    {
        $guard = $this->guard();
        $request = $this->request();

        self::assertSame(0, $guard->consumeChallengeAttempt($request));
        self::assertSame(0, $guard->consumeChallengeAttempt($request));
        self::assertGreaterThan(0, $guard->consumeChallengeAttempt($request));
    }

    private function request(string $ip = '192.0.2.1', string $browser = 'test-browser'): Request
    {
        return Request::create('/boutique', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $browser]);
    }

    private function guard(int $burst = 2, int $sustained = 5): BotTrafficGuard
    {
        $cache = new ArrayAdapter();
        $storage = new CacheStorage($cache);
        $factory = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory([
            'id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute',
        ], $storage);

        return new BotTrafficGuard(
            $cache,
            $factory('burst', $burst),
            $factory('sustained', $sustained),
            $factory('verified_burst', 3),
            $factory('verified_sustained', 6),
            $factory('challenge', 2),
            'test-secret',
        );
    }
}
