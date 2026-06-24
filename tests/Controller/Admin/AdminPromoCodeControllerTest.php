<?php

namespace App\Tests\Controller\Admin;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoDiscountType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminPromoCodeControllerTest extends WebTestCase
{
    public function testAdminCanOpenPromoCodeManagementScreens(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        if (!$connection->createSchemaManager()->tablesExist(['promo_code'])) {
            self::markTestSkipped('Run Doctrine migrations before testing promo-code administration.');
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $email = sprintf('admin-promo-%s@example.com', mb_strtolower($suffix));
        $password = 'admin-password';
        $code = 'PROMO-' . $suffix;
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Promo')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $promoCode = (new PromoCode())
            ->setCode($code)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(15)
            ->setMaxUses(10);

        try {
            $entityManager->persist($admin);
            $entityManager->persist($promoCode);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $token = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $token,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/admin/dashboard');

            $client->request('GET', '/admin/codes-promo');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Codes promo');
            self::assertSelectorTextContains('.admin-category-grid', $code);
            self::assertSelectorExists('a[href="/admin/codes-promo/new"]');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/codes-promo"]');

            $client->request('GET', sprintf('/admin/codes-promo/%d/edit', $promoCode->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="promo_code[code]"]');
            self::assertSelectorExists('select[name="promo_code[discountType]"]');
            self::assertSelectorExists('input[name="promo_code[value]"]');
            self::assertSelectorExists('input[name="promo_code[maxUses]"]');
            self::assertSelectorExists('select[name="promo_code[assignedUser]"]');
        } finally {
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
