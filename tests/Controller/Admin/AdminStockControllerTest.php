<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminStockControllerTest extends WebTestCase
{
    public function testAdminCanUpdateProductQuantityFromStockPage(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-stock-%s@example.com', $suffix);
        $password = 'admin-password';
        $categoryName = sprintf('Stock Category %s', $suffix);
        $licenseName = sprintf('Stock License %s', $suffix);
        $reference = 'STOCK-' . $suffix;
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Stock')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Stock Product')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setTaxRate('20')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(5);

        try {
            $entityManager->persist($admin);
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($product);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);
            self::assertResponseRedirects('/admin/dashboard');

            $crawler = $client->request('GET', '/admin/stock');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', 'Stock');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/stock"]');
            self::assertSelectorExists('.admin-stock-sync');
            self::assertSelectorExists(sprintf('[data-stock-product-id="%d"]', $product->getId()));
            $csrfToken = $crawler->filter('[data-controller="admin-stock"]')->attr('data-admin-stock-token-value');

            $client->request(
                'PATCH',
                sprintf('/admin/stock/products/%d', $product->getId()),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: json_encode(['quantity' => 18], \JSON_THROW_ON_ERROR),
            );

            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame(18, $payload['product']['quantity']);

            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            self::assertSame(18, (int) $connection->fetchOne(
                'SELECT quantity FROM product WHERE id = ?',
                [$product->getId()],
            ));

            $client->request(
                'PATCH',
                sprintf('/admin/stock/products/%d', $product->getId()),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: json_encode(['quantity' => -1], \JSON_THROW_ON_ERROR),
            );
            self::assertResponseStatusCodeSame(422);
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('product', ['reference' => $reference]);
            $connection->delete('category', ['name' => $categoryName]);
            $connection->delete('product_license', ['name' => $licenseName]);
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
