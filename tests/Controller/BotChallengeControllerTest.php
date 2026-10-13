<?php

namespace App\Tests\Controller;

use App\Service\BotTrafficGuard;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class BotChallengeControllerTest extends WebTestCase
{
    public function testRepeatedUnknownUrlsAreChallengedBeforeRouting(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $cache = new ArrayAdapter();
        $storage = new CacheStorage($cache);
        $factory = static fn (string $id): RateLimiterFactory => new RateLimiterFactory([
            'id' => $id, 'policy' => 'sliding_window', 'limit' => 1, 'interval' => '1 minute',
        ], $storage);
        static::getContainer()->set(BotTrafficGuard::class, new BotTrafficGuard(
            $cache, $factory('burst'), $factory('sustained'), $factory('verified_burst'),
            $factory('verified_sustained'), $factory('challenge'), 'test-secret',
        ));

        $client->request('GET', '/unknown-bot-path');
        self::assertResponseRedirects('/boutique');

        $client->request('GET', '/unknown-bot-path');
        self::assertResponseRedirects('/verification?return_to=/unknown-bot-path');
        self::assertResponseHasHeader('x-bot-challenge');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="answer"]');
    }

    public function testTheChallengeWorksWithoutTheDatabaseAndRejectsMissingCsrf(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verification');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img.verification-code[src^="data:image/png;base64,"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
        self::assertResponseHeaderSame('x-robots-tag', 'noindex, nofollow, noarchive');

        $client->request('POST', '/verification', ['answer' => '012345']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.verification-error');
        self::assertNull($client->getCookieJar()->get(BotTrafficGuard::COOKIE_NAME));
    }

    public function testSuccessfulVerificationSetsASignedCookieAndPreservesTheDestination(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_USER_AGENT' => 'test-browser', 'HTTPS' => 'on']);
        $crawler = $client->request('GET', '/verification?return_to=' . rawurlencode('/boutique?q=Naruto'));
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->setChallengeAnswer($client);

        $client->request('POST', '/verification', ['_csrf_token' => $csrf, 'answer' => '012345', 'return_to' => '/boutique?q=Naruto']);

        self::assertResponseRedirects('/boutique?q=Naruto', 303);
        $cookie = $client->getCookieJar()->get(BotTrafficGuard::COOKIE_NAME);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());

        $client->request('POST', '/verification', ['_csrf_token' => $csrf, 'answer' => '012345']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testSuccessfulVerificationRejectsAnExternalRedirect(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/verification');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->setChallengeAnswer($client);

        $client->request('POST', '/verification', ['_csrf_token' => $csrf, 'answer' => '012345', 'return_to' => '//evil.example/phishing']);

        self::assertResponseRedirects('/boutique', 303);
    }

    private function setChallengeAnswer(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        $session = static::getContainer()->get('session.factory')->createSession();
        $cookie = $client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie);
        $session->setId($cookie->getValue());
        $session->start();
        $session->set('bot_captcha', [
            'answer' => hash_hmac('sha256', '012345', static::getContainer()->getParameter('kernel.secret')),
            'expires' => time() + 300,
        ]);
        $session->save();
    }
}
