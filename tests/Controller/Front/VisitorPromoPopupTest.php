<?php

namespace App\Tests\Controller\Front;

use App\Entity\PopupSettings;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoApplicationType;
use App\Service\VisitorActivityTracker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class VisitorPromoPopupTest extends WebTestCase
{
    public function testPopupIsRenderedOnlyForUnauthenticatedVisitors(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['promo_code', 'popup_settings', 'site_visitor'])) {
            self::markTestSkipped('Run Doctrine migrations before testing the visitor popup.');
        }

        $visitorColumns = array_change_key_case($schemaManager->listTableColumns('site_visitor'), \CASE_LOWER);

        if (!isset($visitorColumns['popup_promo_code'], $visitorColumns['popup_promo_viewed_at'], $visitorColumns['popup_promo_copied_at'])) {
            self::markTestSkipped('Run the popup engagement migration before testing visitor copy tracking.');
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $email = sprintf('popup-visitor-%s@example.com', mb_strtolower($suffix));
        $password = 'visitor-password';
        $code = 'SHIP-' . $suffix;
        $title = 'Livraison surprise ' . $suffix;
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $promoCode = (new PromoCode())
            ->setCode($code)
            ->setApplicationType(PromoApplicationType::SHIPPING);
        $settings = (new PopupSettings())
            ->setActive(true)
            ->setTitle($title)
            ->setMessage('Une remise dédiée aux frais de livraison.')
            ->setPromoCode($promoCode);
        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $visitorToken = null;

        try {
            $connection->executeStatement('DELETE FROM popup_settings');
            $entityManager->persist($promoCode);
            $entityManager->persist($settings);
            $entityManager->persist($user);
            $entityManager->flush();

            $crawler = $client->request('GET', '/boutique', server: [
                'HTTP_ACCEPT' => 'text/html',
                'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/125.0 Safari/537.36',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#visitor-promo-popup-title', $title);
            self::assertSelectorTextContains('[data-promo-popup-copy]', $code);
            self::assertSelectorExists('#visitor-promo-popup .fa-truck-fast');

            $popup = $crawler->filter('#visitor-promo-popup');
            $engagementUrl = $popup->attr('data-engagement-url');
            $engagementCsrf = $popup->attr('data-engagement-csrf');
            $version = $popup->attr('data-version');
            $visitorCookie = $client->getCookieJar()->get(VisitorActivityTracker::COOKIE_NAME);
            self::assertNotNull($visitorCookie);
            $visitorToken = $visitorCookie->getValue();

            $client->request(
                'POST',
                $engagementUrl,
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $engagementCsrf,
                ],
                content: json_encode(['event' => 'viewed', 'version' => $version], \JSON_THROW_ON_ERROR),
            );

            self::assertResponseStatusCodeSame(204);
            $engagement = $connection->fetchAssociative(
                'SELECT popup_promo_code, popup_promo_viewed_at, popup_promo_copied_at FROM site_visitor WHERE visitor_token = ?',
                [$visitorToken],
            );
            self::assertIsArray($engagement);
            self::assertSame($code, $engagement['popup_promo_code']);
            self::assertNotNull($engagement['popup_promo_viewed_at']);
            self::assertNull($engagement['popup_promo_copied_at']);

            $client->request(
                'POST',
                $engagementUrl,
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $engagementCsrf,
                ],
                content: json_encode(['event' => 'copied', 'version' => $version], \JSON_THROW_ON_ERROR),
            );

            self::assertResponseStatusCodeSame(204);
            self::assertNotNull($connection->fetchOne(
                'SELECT popup_promo_copied_at FROM site_visitor WHERE visitor_token = ?',
                [$visitorToken],
            ));

            $crawler = $client->request('GET', '/profil');
            $token = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $token,
                'email' => $email,
                'password' => $password,
            ]);
            $client->request('GET', '/');

            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#visitor-promo-popup');

            $client->request('GET', '/admin/clients/viewer?filter=all');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.admin-viewer-table', $code);
            self::assertSelectorTextContains('.admin-viewer-promo__status.is-copied', 'Copié');
        } finally {
            if (is_string($visitorToken)) {
                $connection->delete('site_visitor', ['visitor_token' => $visitorToken]);
            }

            $connection->executeStatement('DELETE FROM popup_settings');
            $connection->delete('promo_code', ['code' => $code]);
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    private function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $this->connection()->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
