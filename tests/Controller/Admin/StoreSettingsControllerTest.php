<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StoreSettingsControllerTest extends WebTestCase
{
    public function testAdminCanCloseTheStorefrontAndStillAccessTheBackOffice(): void
    {
        $client = static::createClient();
        $this->skipIfStoreSettingsAreUnavailable();
        $connection = $this->connection();
        $originalSettings = $connection->fetchAllAssociative('SELECT * FROM store_settings ORDER BY id ASC');
        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-store-settings-%s@example.com', $suffix);
        $password = 'admin-password';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Boutique')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        try {
            $connection->executeStatement('DELETE FROM store_settings');
            $connection->insert('store_settings', [
                'maintenance_enabled' => 0,
                'maintenance_starts_at' => null,
                'maintenance_ends_at' => null,
                'updated_at' => '2026-08-17 08:00:00',
            ]);
            $entityManager->persist($admin);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/admin/dashboard');

            $crawler = $client->request('GET', '/admin/parametres-boutique');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Paramètres boutique');
            self::assertSelectorExists('.admin-sidebar__footer a.is-active[href="/admin/parametres-boutique"]');
            self::assertSelectorExists('input[name="store_settings[maintenanceEnabled]"]');
            self::assertSelectorExists('input[name="store_settings[maintenanceStartsAt]"]');
            self::assertSelectorExists('input[name="store_settings[maintenanceEndsAt]"]');

            $form = $crawler->selectButton('Enregistrer les paramètres')->form([
                'store_settings[maintenanceEnabled]' => '1',
                'store_settings[maintenanceStartsAt]' => '2026-08-18T10:00',
                'store_settings[maintenanceEndsAt]' => '2026-08-20T18:00',
            ]);
            $client->submit($form);

            self::assertResponseRedirects('/admin/parametres-boutique');
            self::assertSame(1, (int) $connection->fetchOne('SELECT maintenance_enabled FROM store_settings LIMIT 1'));

            $client->request('GET', '/admin/dashboard');
            self::assertResponseIsSuccessful();

            $client->request('GET', '/boutique');
            self::assertResponseIsSuccessful();

            static::ensureKernelShutdown();
            $guestClient = static::createClient();
            $guestClient->request('GET', '/boutique');

            self::assertResponseStatusCodeSame(503);
            self::assertResponseHeaderSame('x-robots-tag', 'noindex, nofollow, noarchive');
            self::assertSelectorTextContains('h1', 'On revient très vite !');
            self::assertSelectorExists('img[src*="site_fermeture"]');
            self::assertSelectorTextContains('.maintenance-cover__dates', '18/08/2026');
            self::assertSelectorTextContains('.maintenance-cover__dates', '20/08/2026');

            $guestClient->request('GET', '/api/search/products?q=naruto', server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseStatusCodeSame(503);
            self::assertResponseHeaderSame('content-type', 'application/json');
            self::assertJsonStringEqualsJsonString(
                '{"error":"storefront_maintenance","message":"La boutique est temporairement fermée pour maintenance.","starts_at":"2026-08-18T08:00:00+00:00","ends_at":"2026-08-20T16:00:00+00:00"}',
                (string) $guestClient->getResponse()->getContent(),
            );

            $crawler = $guestClient->request('GET', '/profil');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[action="/auth/login"]');

            $loginToken = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $guestClient->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);
            self::assertResponseRedirects('/admin/dashboard');

            $crawler = $guestClient->request('GET', '/admin/parametres-boutique');
            $form = $crawler->selectButton('Enregistrer les paramètres')->form();
            $form['store_settings[maintenanceEnabled]']->untick();
            $guestClient->submit($form);

            self::assertResponseRedirects('/admin/parametres-boutique');
            self::assertSame(0, (int) $connection->fetchOne('SELECT maintenance_enabled FROM store_settings LIMIT 1'));

            static::ensureKernelShutdown();
            $reopenedClient = static::createClient();
            $reopenedClient->request('GET', '/boutique');
            self::assertResponseIsSuccessful();
        } finally {
            $connection = $this->connection();
            $connection->executeStatement('DELETE FROM store_settings');

            foreach ($originalSettings as $row) {
                $connection->insert('store_settings', $row);
            }

            $connection->delete('app_user', ['email' => $email]);
        }
    }

    private function skipIfStoreSettingsAreUnavailable(): void
    {
        try {
            $connection = $this->connection();
            $connection->executeQuery('SELECT 1');

            if (!$connection->createSchemaManager()->tablesExist(['store_settings'])) {
                self::markTestSkipped('Run Doctrine migrations before testing store settings.');
            }
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
