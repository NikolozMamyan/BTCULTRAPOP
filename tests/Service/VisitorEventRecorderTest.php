<?php

namespace App\Tests\Service;

use App\Service\VisitorEventRecorder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class VisitorEventRecorderTest extends TestCase
{
    public function testRequestsRecordTheIpMethodAndStatusWithoutQueryOrBodySecrets(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('insert')->with('site_visitor_event', self::callback(
            static fn (array $row): bool => '2001:db8::1' === $row['ip_address']
                && '/api/cart/items' === $row['request_path']
                && 'api_request' === $row['event_type']
                && 'POST' === $row['request_method']
                && 201 === $row['http_status']
                && !str_contains(json_encode($row), 'secret-value'),
        ))->willReturn(1);
        $request = Request::create('/api/cart/items?token=secret-value', 'POST', ['password' => 'secret-value'], server: ['REMOTE_ADDR' => '2001:0DB8::1']);
        $request->attributes->set(VisitorEventRecorder::TRACK_ATTRIBUTE, true);

        (new VisitorEventRecorder($connection, new ArrayAdapter()))->record($request, new Response(status: 201));
    }

    public function testRepeatedRefusalsAreSampledPerIpAndEventType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(3))->method('insert')->willReturn(1);
        $recorder = new VisitorEventRecorder($connection, new ArrayAdapter());

        foreach (['192.0.2.1', '192.0.2.2'] as $ip) {
            $request = Request::create('/boutique', server: ['REMOTE_ADDR' => $ip]);
            $request->attributes->set(VisitorEventRecorder::TRACK_ATTRIBUTE, true);
            $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'blocked_request');

            for ($index = 0; $index < 20; ++$index) {
                $recorder->record($request, new Response(status: 403));
            }
        }

        $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'rate_limited');
        $recorder->record($request, new Response(status: 429));
    }

    public function testStaticRequestsAndOrdinaryAdminBrowsingAreNotRecorded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('insert');
        $recorder = new VisitorEventRecorder($connection, new ArrayAdapter());
        $recorder->record(Request::create('/assets/app.css', server: ['REMOTE_ADDR' => '192.0.2.1']), new Response());
        $request = Request::create('/admin/clients/viewer', server: ['REMOTE_ADDR' => '192.0.2.1']);
        $request->attributes->set(VisitorEventRecorder::TRACK_ATTRIBUTE, true);
        $recorder->record($request, new Response());
    }

    public function testPasswordResetTokensAreRedactedFromThePath(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('insert')->with('site_visitor_event', self::callback(
            static fn (array $row): bool => '/auth/reinitialiser-mot-de-passe/[token]' === $row['request_path'],
        ))->willReturn(1);
        $recorder = new VisitorEventRecorder($connection, new ArrayAdapter());

        foreach ([str_repeat('a', 32) . '.' . str_repeat('b', 64), str_repeat('%61', 32) . '.' . str_repeat('%62', 64)] as $token) {
            $request = Request::create('/auth/reinitialiser-mot-de-passe/' . $token, server: ['REMOTE_ADDR' => '192.0.2.1']);
            $request->attributes->set(VisitorEventRecorder::TRACK_ATTRIBUTE, true);
            $recorder->record($request, new Response());
        }
    }
}
