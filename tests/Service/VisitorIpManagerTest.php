<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\VisitorIpManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class VisitorIpManagerTest extends TestCase
{
    public function testItNormalizesIpv6AndRejectsInvalidAddresses(): void
    {
        self::assertSame('2001:db8::1', VisitorIpManager::normalize(' 2001:0DB8:0:0:0:0:0:1 '));
        self::assertSame('192.0.2.1', VisitorIpManager::normalize('192.0.2.1'));

        $this->expectException(\InvalidArgumentException::class);
        VisitorIpManager::normalize('192.0.2.1/24');
    }

    public function testBlockingInvalidatesTheSharedCacheAndRecordsTheAction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchFirstColumn')->willReturnOnConsecutiveCalls([], ['192.0.2.1']);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback) => $callback());
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('INSERT INTO visitor_ip_block'),
            self::callback(static fn (array $parameters): bool => '192.0.2.1' === $parameters[0]),
        )->willReturn(1);
        $connection->expects(self::once())->method('insert')->with('site_visitor_event', self::callback(
            static fn (array $row): bool => '192.0.2.1' === $row['ip_address'] && 'ip_blocked' === $row['event_type'],
        ))->willReturn(1);
        $cache = new ArrayAdapter();
        $manager = new VisitorIpManager($connection, $cache);
        $anotherWorker = new VisitorIpManager($connection, $cache);

        self::assertFalse($anotherWorker->isBlocked('192.0.2.1'));
        self::assertFalse($anotherWorker->isBlocked('192.0.2.1'));
        $manager->change('192.0.2.1', 'block', new User(), '192.0.2.2');
        self::assertTrue($anotherWorker->isBlocked('192.0.2.1'));
    }

    public function testUnblockingInvalidatesTheCachedBlockAndRecordsTheAction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(['2001:db8::1'], []);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback) => $callback());
        $connection->expects(self::once())->method('delete')->with('visitor_ip_block', ['ip_address' => '2001:db8::1'])->willReturn(1);
        $connection->expects(self::once())->method('insert')->with('site_visitor_event', self::callback(
            static fn (array $row): bool => 'ip_unblocked' === $row['event_type'],
        ))->willReturn(1);
        $manager = new VisitorIpManager($connection, new ArrayAdapter());

        self::assertTrue($manager->isBlocked('2001:0DB8::1'));
        $manager->change('2001:db8::1', 'unblock', new User(), '192.0.2.2');
        self::assertFalse($manager->isBlocked('2001:db8::1'));
    }

    public function testAnAdministratorCannotBlockTheirOwnIp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');
        $manager = new VisitorIpManager($connection, new ArrayAdapter());

        $this->expectExceptionMessage('admin.viewer.ip.self_block');
        $manager->change('2001:0DB8::1', 'block', new User(), '2001:db8::1');
    }

    public function testAnInvalidActionIsRejectedBeforeAnyDatabaseWrite(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');

        $this->expectExceptionMessage('admin.viewer.ip.invalid_action');
        (new VisitorIpManager($connection, new ArrayAdapter()))->change('192.0.2.1', 'delete-everything', new User(), '192.0.2.2');
    }
}
