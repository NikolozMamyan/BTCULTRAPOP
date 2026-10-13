<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Security\UserSessionManager;
use App\Service\BotTrafficGuard;
use App\Service\VisitorActivityTracker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminVisitorControllerTest extends WebTestCase
{
    private array $createdEmails = [];
    private const TARGET_IP = '198.51.100.77';
    private const ADMIN_IP = '192.0.2.200';

    public function testVisitorsShowIpsAndAdministratorsCanBlockAndUnblockFromTheirHistory(): void
    {
        $client = $this->authenticatedClient();
        $visit = Request::create('/boutique', server: ['REMOTE_ADDR' => self::TARGET_IP, 'HTTP_USER_AGENT' => 'visitor-tests', 'HTTP_ACCEPT' => 'text/html', 'HTTP_ACCEPT_LANGUAGE' => 'fr']);
        $visit->attributes->set('_route', 'app_front_boutique');
        static::getContainer()->get(VisitorActivityTracker::class)->track($visit, new Response(), null);

        $client->request('GET', '/admin/clients/viewer?filter=all');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-viewer-table', self::TARGET_IP);
        self::assertSelectorExists('a[href="/admin/clients/viewer/historique"]');

        $client->request('GET', '/visitor-history-test-path?token=do-not-log', server: ['REMOTE_ADDR' => self::TARGET_IP]);
        self::assertResponseRedirects('/boutique');

        $crawler = $client->request('GET', '/admin/clients/viewer/historique?ip=' . self::TARGET_IP);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-ip-table', '/visitor-history-test-path');
        self::assertSelectorTextNotContains('.admin-ip-table', 'do-not-log');
        $client->submit($crawler->selectButton('Bloquer')->form());

        self::assertResponseRedirects('/admin/clients/viewer/historique?ip=' . self::TARGET_IP);
        self::assertNotFalse($this->connection()->fetchOne('SELECT id FROM visitor_ip_block WHERE ip_address = ?', [self::TARGET_IP]));

        $request = Request::create('/boutique', server: ['REMOTE_ADDR' => self::TARGET_IP, 'HTTP_USER_AGENT' => 'visitor-tests']);
        $cookie = static::getContainer()->get(BotTrafficGuard::class)->verificationCookie($request);
        $client->getCookieJar()->set(Cookie::fromString((string) $cookie, 'http://localhost'));
        $client->request('GET', '/api/search/products', server: ['REMOTE_ADDR' => self::TARGET_IP, 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('ip_blocked', json_decode($client->getResponse()->getContent(), true)['error']);

        $client->request('GET', '/verification', server: ['REMOTE_ADDR' => self::TARGET_IP]);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/clients/viewer/historique?ip=' . self::TARGET_IP);
        self::assertSelectorTextContains('.admin-ip-table', 'IP bloquée manuellement');
        self::assertSelectorTextContains('.admin-ip-table', 'Accès refusé');

        $crawler = $client->request('GET', '/admin/clients/viewer/ip-bloquees');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-ip-table', self::TARGET_IP);
        $client->submit($crawler->selectButton('Débloquer')->form());
        self::assertResponseRedirects('/admin/clients/viewer/ip-bloquees');
        self::assertFalse($this->connection()->fetchOne('SELECT id FROM visitor_ip_block WHERE ip_address = ?', [self::TARGET_IP]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM site_visitor_event WHERE ip_address = ? AND event_type = ?', [self::TARGET_IP, 'ip_unblocked']));

        $client->request('GET', '/visitor-history-test-path', server: ['REMOTE_ADDR' => self::TARGET_IP]);
        self::assertResponseRedirects('/boutique');
    }

    public function testBlockActionsRequireCsrfAndRejectSelfBlocking(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/admin/clients/viewer/blocage-ip', ['ip' => self::TARGET_IP, 'action' => 'block']);
        self::assertResponseRedirects('/admin/clients/viewer?filter=humans');
        self::assertFalse($this->connection()->fetchOne('SELECT id FROM visitor_ip_block WHERE ip_address = ?', [self::TARGET_IP]));

        $crawler = $client->request('GET', '/admin/clients/viewer/historique?ip=' . self::ADMIN_IP);
        $client->submit($crawler->selectButton('Bloquer')->form());
        self::assertResponseRedirects('/admin/clients/viewer/historique?ip=' . self::ADMIN_IP);
        self::assertFalse($this->connection()->fetchOne('SELECT id FROM visitor_ip_block WHERE ip_address = ?', [self::ADMIN_IP]));
        $client->followRedirect();
        self::assertSelectorTextContains('.admin-flash', 'propre adresse IP');
    }

    public function testGuestsAndCustomersCannotAccessOrChangeVisitorIpData(): void
    {
        $client = static::createClient();
        $this->skipIfSchemaUnavailable();
        $client->request('GET', '/admin/clients/viewer/historique');
        self::assertResponseRedirects('/profil');
        $client->request('POST', '/admin/clients/viewer/blocage-ip', ['ip' => self::TARGET_IP, 'action' => 'block']);
        self::assertResponseRedirects('/profil');

        static::ensureKernelShutdown();
        $client = $this->authenticatedClient(['ROLE_USER']);
        $client->request('GET', '/admin/clients/viewer/historique');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin/clients/viewer/ip-bloquees');
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/admin/clients/viewer/blocage-ip', ['ip' => self::TARGET_IP, 'action' => 'block']);
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->connection()->fetchOne('SELECT id FROM visitor_ip_block WHERE ip_address = ?', [self::TARGET_IP]));
    }

    public function testHistoryIsPaginatedAndLimitedToTheLastThirtyDays(): void
    {
        $client = $this->authenticatedClient();
        $connection = $this->connection();
        $recent = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        for ($index = 0; $index < 51; ++$index) {
            $connection->insert('site_visitor_event', ['ip_address' => self::TARGET_IP, 'event_type' => 'request', 'request_path' => '/recent-' . $index, 'request_method' => 'GET', 'http_status' => 200, 'occurred_at' => $recent]);
        }
        $connection->insert('site_visitor_event', ['ip_address' => self::TARGET_IP, 'event_type' => 'request', 'request_path' => '/expired-history', 'request_method' => 'GET', 'http_status' => 200, 'occurred_at' => '2000-01-01 00:00:00']);

        $crawler = $client->request('GET', '/admin/clients/viewer/historique?ip=' . self::TARGET_IP);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(50, '.admin-ip-table tbody tr');
        self::assertSelectorTextNotContains('.admin-ip-table', '/expired-history');
        $client->click($crawler->selectLink('Voir les entrées plus anciennes')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.admin-ip-table tbody tr');
        self::assertSelectorTextContains('.admin-ip-table', '/recent-0');

        $tester = new CommandTester((new Application(static::$kernel))->find('app:visitors:purge-history'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM site_visitor_event WHERE ip_address = ? AND request_path = ?', [self::TARGET_IP, '/expired-history']));
        self::assertSame(51, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM site_visitor_event WHERE ip_address = ?', [self::TARGET_IP]));
    }

    private function authenticatedClient(array $roles = ['ROLE_ADMIN']): KernelBrowser
    {
        $client = static::createClient([], ['REMOTE_ADDR' => self::ADMIN_IP, 'HTTP_USER_AGENT' => 'visitor-tests']);
        $this->skipIfSchemaUnavailable();
        $email = 'visitor-admin-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->createdEmails[] = $email;
        $user = (new User())->setEmail($email)->setFirstName('Admin')->setLastName('Visitors')->setPassword('unused-password-hash')->setRoles($roles);
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($user);
        $manager->flush();
        $request = Request::create('/admin', server: ['REMOTE_ADDR' => self::ADMIN_IP, 'HTTP_USER_AGENT' => 'visitor-tests']);
        $cookie = static::getContainer()->get(UserSessionManager::class)->createSession($user, $request);
        $client->getCookieJar()->set(Cookie::fromString((string) $cookie, 'http://localhost'));

        return $client;
    }

    private function skipIfSchemaUnavailable(): void
    {
        try {
            if (!$this->connection()->createSchemaManager()->tablesExist(['site_visitor_event', 'visitor_ip_block', 'site_visitor'])) {
                self::markTestSkipped('Run the visitor history migration in the test database.');
            }
        } catch (\Doctrine\DBAL\Exception) {
            self::markTestSkipped('The test database is unavailable.');
        }
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        if ($this->createdEmails) {
            $connection = $this->connection();
            $connection->delete('visitor_ip_block', ['ip_address' => self::TARGET_IP]);
            $connection->delete('site_visitor_event', ['ip_address' => self::TARGET_IP]);
            $connection->delete('site_visitor', ['ip_address' => self::TARGET_IP]);
            foreach ($this->createdEmails as $email) {
                $connection->delete('app_user', ['email' => $email]);
            }
        }

        parent::tearDown();
    }
}
