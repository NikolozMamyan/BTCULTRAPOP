<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminShippingControllerTest extends WebTestCase
{
    public function testAdminCanUpdateShippingMinimumAndTiers(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        if (!$connection->createSchemaManager()->tablesExist(['shipping_settings', 'shipping_rate_tier'])) {
            self::markTestSkipped('Run Doctrine migrations before testing shipping administration.');
        }

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-shipping-%s@example.com', $suffix);
        $password = 'admin-password';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Livraison')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        try {
            $connection->executeStatement('DELETE FROM shipping_settings');
            $entityManager->persist($admin);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $token = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $token,
                'email' => $email,
                'password' => $password,
            ]);

            $crawler = $client->request('GET', '/admin/shipping');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', 'Livraison');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/shipping"]');
            self::assertSelectorCount(5, '.admin-shipping-tier');
            self::assertSelectorExists('input[name="shipping_settings[minimumOrderCents]"]');

            $form = $crawler->selectButton('Enregistrer le barème')->form([
                'shipping_settings[minimumOrderCents]' => '12',
                'shipping_settings[tiers][0][thresholdCents]' => '12',
                'shipping_settings[tiers][0][shippingAmountCents]' => '7',
                'shipping_settings[tiers][1][thresholdCents]' => '25',
                'shipping_settings[tiers][1][shippingAmountCents]' => '4',
                'shipping_settings[tiers][2][thresholdCents]' => '50',
                'shipping_settings[tiers][2][shippingAmountCents]' => '0',
            ]);

            foreach ([3, 4] as $removedTier) {
                unset(
                    $form[sprintf('shipping_settings[tiers][%d][thresholdCents]', $removedTier)],
                    $form[sprintf('shipping_settings[tiers][%d][shippingAmountCents]', $removedTier)],
                );
            }

            $client->submit($form);

            self::assertResponseRedirects('/admin/shipping');
            self::assertSame(1200, (int) $connection->fetchOne('SELECT minimum_order_cents FROM shipping_settings LIMIT 1'));
            $tiers = $connection->fetchAllAssociative('SELECT threshold_cents, shipping_amount_cents FROM shipping_rate_tier ORDER BY position');
            self::assertCount(3, $tiers);
            self::assertSame(1200, (int) $tiers[0]['threshold_cents']);
            self::assertSame(700, (int) $tiers[0]['shipping_amount_cents']);
            self::assertSame(0, (int) $tiers[2]['shipping_amount_cents']);
        } finally {
            $connection->executeStatement('DELETE FROM shipping_settings');
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
