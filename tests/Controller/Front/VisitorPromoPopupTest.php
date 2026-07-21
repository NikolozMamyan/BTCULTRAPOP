<?php

namespace App\Tests\Controller\Front;

use App\Entity\PopupSettings;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoApplicationType;
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

        if (!$connection->createSchemaManager()->tablesExist(['promo_code', 'popup_settings'])) {
            self::markTestSkipped('Run Doctrine migrations before testing the visitor popup.');
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
        $user = (new User())->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        try {
            $connection->executeStatement('DELETE FROM popup_settings');
            $entityManager->persist($promoCode);
            $entityManager->persist($settings);
            $entityManager->persist($user);
            $entityManager->flush();

            $client->request('GET', '/');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#visitor-promo-popup-title', $title);
            self::assertSelectorTextContains('[data-promo-popup-copy]', $code);
            self::assertSelectorExists('#visitor-promo-popup .fa-truck-fast');

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
        } finally {
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
