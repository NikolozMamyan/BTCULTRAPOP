<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminDashboardControllerTest extends WebTestCase
{
    public function testAdminUserIsRedirectedToDashboardAfterLogin(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $email = sprintf('admin-%s@example.com', bin2hex(random_bytes(4)));
        $password = 'admin-password';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('ULTRAPOP')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        try {
            $entityManager->persist($admin);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');

            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/admin/dashboard');

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Dashboard');
            self::assertSelectorExists('.admin-sidebar');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/dashboard"]');
            self::assertSelectorTextContains('.admin-topbar', 'Admin ULTRAPOP');
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    private function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }
}
