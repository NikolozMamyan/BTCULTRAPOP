<?php

namespace App\Tests\Controller\Admin;

use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminPopupControllerTest extends WebTestCase
{
    public function testAdminCanConfigureTheVisitorPopup(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        if (!$connection->createSchemaManager()->tablesExist(['promo_code', 'popup_settings'])) {
            self::markTestSkipped('Run Doctrine migrations before testing popup administration.');
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $email = sprintf('admin-popup-%s@example.com', mb_strtolower($suffix));
        $password = 'admin-password';
        $code = 'POPUP-' . $suffix;
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Popup')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));
        $promoCode = (new PromoCode())->setCode($code);

        try {
            $connection->executeStatement('DELETE FROM popup_settings');
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

            $crawler = $client->request('GET', '/admin/popup');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Popup promotionnel');
            self::assertSelectorExists('input[name="popup_settings[active]"]');
            self::assertSelectorExists('input[name="popup_settings[title]"]');
            self::assertSelectorExists('textarea[name="popup_settings[message]"]');
            self::assertSelectorExists('select[name="popup_settings[promoCode]"]');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/popup"]');

            $form = $crawler->selectButton('Enregistrer le popup')->form([
                'popup_settings[active]' => '1',
                'popup_settings[title]' => 'Offre visiteurs',
                'popup_settings[message]' => 'Copiez ce code et profitez de votre avantage.',
                'popup_settings[promoCode]' => (string) $promoCode->getId(),
            ]);
            $client->submit($form);

            self::assertResponseRedirects('/admin/popup');
            $settings = $connection->fetchAssociative('SELECT active, title, promo_code_id FROM popup_settings LIMIT 1');
            self::assertIsArray($settings);
            self::assertSame(1, (int) $settings['active']);
            self::assertSame('Offre visiteurs', $settings['title']);
            self::assertSame($promoCode->getId(), (int) $settings['promo_code_id']);
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
