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

final class AdminPriceControllerTest extends WebTestCase
{
    public function testAdminCanUpdateOneProductAndThenAWholeCategory(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-price-%s@example.com', $suffix);
        $password = 'admin-password';
        $categoryName = sprintf('Price Category %s', $suffix);
        $licenseName = sprintf('Price License %s', $suffix);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Price')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $firstProduct = $this->createProduct('PRICE-A-' . $suffix, 'Price Product A', $category, $license);
        $secondProduct = $this->createProduct('PRICE-B-' . $suffix, 'Price Product B', $category, $license);

        try {
            $entityManager->persist($admin);
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($firstProduct);
            $entityManager->persist($secondProduct);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);
            self::assertResponseRedirects('/admin/dashboard');

            $crawler = $client->request('GET', '/admin/prices');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', 'Prix');
            self::assertSelectorCount(2, sprintf('[data-price-category-id="%d"] [data-price-product-id]', $category->getId()));
            self::assertSelectorExists(sprintf('[data-price-product-id="%d"]', $firstProduct->getId()));
            $csrfToken = $crawler->filter('[data-controller="admin-prices"]')->attr('data-admin-prices-token-value');

            $client->request(
                'PATCH',
                sprintf('/admin/prices/products/%d', $firstProduct->getId()),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: json_encode([
                    'priceTaxExcluded' => '15,50',
                    'taxRate' => '20',
                ], \JSON_THROW_ON_ERROR),
            );
            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame('15.50', $payload['product']['priceTaxExcluded']);
            self::assertSame('20.00', $payload['product']['taxRate']);
            self::assertSame('18.60', $payload['product']['priceTaxIncluded']);

            $client->request(
                'PATCH',
                sprintf('/admin/prices/categories/%d', $category->getId()),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $csrfToken,
                ],
                content: json_encode([
                    'priceTaxExcluded' => '8',
                    'taxRate' => '5.5',
                ], \JSON_THROW_ON_ERROR),
            );
            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertCount(2, $payload['products']);

            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $rows = $connection->fetchAllAssociative(
                'SELECT price_tax_excluded, tax_rate, price_tax_included
                 FROM product
                 WHERE id IN (?, ?)
                 ORDER BY id',
                [$firstProduct->getId(), $secondProduct->getId()],
            );

            self::assertCount(2, $rows);
            foreach ($rows as $row) {
                self::assertSame('8.000000', $row['price_tax_excluded']);
                self::assertSame('5.50', $row['tax_rate']);
                self::assertSame('8.440000', $row['price_tax_included']);
            }
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('product', ['reference' => 'PRICE-A-' . $suffix]);
            $connection->delete('product', ['reference' => 'PRICE-B-' . $suffix]);
            $connection->delete('category', ['name' => $categoryName]);
            $connection->delete('product_license', ['name' => $licenseName]);
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    private function createProduct(string $reference, string $name, Category $category, License $license): Product
    {
        return (new Product())
            ->setName($name)
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setTaxRate('20')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(5);
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
