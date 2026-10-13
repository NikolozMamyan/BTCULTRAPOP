<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\BotProtectionSubscriber;
use App\Service\BotTrafficGuard;
use App\Service\VisitorIpManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\Translator;

final class BotProtectionSubscriberTest extends TestCase
{
    public function testStaticFilesAndWebhooksDoNotConsumeTheVisitorLimit(): void
    {
        $subscriber = $this->subscriber();

        foreach (['/assets/styles/app.css', '/img/logo/logotype.svg', '/stripe/webhook', '/merchant-center/products.xml', '/verification', '/robots.txt'] as $path) {
            for ($index = 0; $index < 3; ++$index) {
                $event = $this->event($path);
                $subscriber->onKernelRequest($event);
                self::assertFalse($event->hasResponse());
            }
        }

        $event = $this->event('/boutique');
        $subscriber->onKernelRequest($event);
        self::assertFalse($event->hasResponse());
    }

    public function testSuspiciousApiRequestsReturnAChallengeWithoutExecutingTheController(): void
    {
        $subscriber = $this->subscriber();
        $subscriber->onKernelRequest($this->event('/api/search/products'));
        $event = $this->event('/api/search/products');
        $subscriber->onKernelRequest($event);

        self::assertSame(429, $event->getResponse()->getStatusCode());
        self::assertSame('/verification', $event->getResponse()->headers->get('X-Bot-Challenge'));
        self::assertTrue($event->getResponse()->headers->has('Retry-After'));
        self::assertStringContainsString('no-store', $event->getResponse()->headers->get('Cache-Control'));
        self::assertSame('bot_verification_required', json_decode($event->getResponse()->getContent(), true)['error']);
    }

    public function testSuspiciousHtmlNavigationRedirectsToTheChallenge(): void
    {
        $subscriber = $this->subscriber();
        $subscriber->onKernelRequest($this->event('/boutique'));
        $event = $this->event('/boutique');
        $subscriber->onKernelRequest($event);

        self::assertSame(302, $event->getResponse()->getStatusCode());
        self::assertSame('/verification', $event->getResponse()->headers->get('Location'));
    }

    public function testSubrequestsAreNotCounted(): void
    {
        $subscriber = $this->subscriber();

        for ($index = 0; $index < 3; ++$index) {
            $event = $this->event('/boutique', HttpKernelInterface::SUB_REQUEST);
            $subscriber->onKernelRequest($event);
            self::assertFalse($event->hasResponse());
        }

        $event = $this->event('/boutique');
        $subscriber->onKernelRequest($event);
        self::assertFalse($event->hasResponse());
    }

    private function event(string $path, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create($path, server: ['REMOTE_ADDR' => '192.0.2.1']), $type);
    }

    public function testBlockedIpsCannotUseTheCaptchaOrApi(): void
    {
        $subscriber = $this->subscriber(['192.0.2.1']);

        foreach (['/verification', '/api/search/products', '/boutique'] as $path) {
            $event = $this->event($path);
            $subscriber->onKernelRequest($event);

            self::assertSame(403, $event->getResponse()->getStatusCode());
            self::assertFalse($event->getResponse()->headers->has('X-Bot-Challenge'));
            self::assertSame('blocked_request', $event->getRequest()->attributes->get('_visitor_event_type'));
        }
    }

    private function subscriber(array $blockedIps = []): BotProtectionSubscriber
    {
        $cache = new ArrayAdapter();
        $storage = new CacheStorage($cache);
        $factory = static fn (string $id): RateLimiterFactory => new RateLimiterFactory([
            'id' => $id, 'policy' => 'sliding_window', 'limit' => 1, 'interval' => '1 minute',
        ], $storage);
        $guard = new BotTrafficGuard($cache, $factory('burst'), $factory('sustained'), $factory('verified_burst'), $factory('verified_sustained'), $factory('challenge'), 'test-secret');
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/verification');

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($blockedIps);

        return new BotProtectionSubscriber($guard, $urls, new Translator('fr'), new VisitorIpManager($connection, $cache));
    }
}
