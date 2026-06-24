<?php

namespace App\Tests\Entity;

use App\Entity\NewsletterSubscriber;
use PHPUnit\Framework\TestCase;

final class NewsletterSubscriberTest extends TestCase
{
    public function testItNormalizesAndCanReactivateASubscription(): void
    {
        $subscriber = (new NewsletterSubscriber())
            ->setEmail('  FAN@Example.COM ')
            ->setLocale('invalid')
            ->setSource('invalid');

        self::assertSame('fan@example.com', $subscriber->getEmail());
        self::assertSame('fr', $subscriber->getLocale());
        self::assertSame('footer', $subscriber->getSource());
        self::assertTrue($subscriber->isActive());

        $subscriber->unsubscribe(new \DateTimeImmutable('2026-06-24 12:00:00'));

        self::assertFalse($subscriber->isActive());
        self::assertNotNull($subscriber->getUnsubscribedAt());

        $subscriber->subscribe(new \DateTimeImmutable('2026-06-24 13:00:00'));

        self::assertTrue($subscriber->isActive());
        self::assertNull($subscriber->getUnsubscribedAt());
        self::assertSame('2026-06-24 13:00:00', $subscriber->getSubscribedAt()->format('Y-m-d H:i:s'));
    }
}
