<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminProductControllerTest extends WebTestCase
{
    public function testAdminCanOpenProductManagementScreens(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-product-%s@example.com', $suffix);
        $password = 'admin-password';
        $reference = sprintf('ADMIN-%s', strtoupper($suffix));
        $categoryName = sprintf('Admin Category %s', $suffix);
        $licenseName = sprintf('Admin License %s', $suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Product')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Admin Product Fixture')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setTaxRate('20')
            ->setIngredients('Eau, sucre, arôme naturel.')
            ->setQuantity(7);
        $product->addImage(
            (new ProductImage())
                ->setPath('https://ultrapop.com/img/p/1/7/0/170.jpg')
                ->setAlt('Admin Product Fixture')
                ->setCover(true),
        );

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

            $client->request('GET', '/admin/products');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Produits');
            self::assertSelectorTextContains('.admin-product-table', 'Admin Product Fixture');
            self::assertSelectorExists(sprintf('a[href="/admin/products/%d/edit"]', $product->getId()));
            self::assertSelectorExists(sprintf('button[data-admin-products-url-param="/admin/products/%d/toggle"]', $product->getId()));
            self::assertSelectorExists('a[href="/admin/products/new"]');
            self::assertSelectorExists('button[data-action="admin-products#openScanner"]');
            self::assertSelectorExists('.admin-scanner-modal[data-admin-products-target="scanner"]');

            $client->request('GET', sprintf('/admin/products/%d/edit', $product->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form.admin-product-form');
            self::assertSelectorExists('input[name="product[name]"]');
            self::assertSelectorExists('input[name="product[active]"]');
            self::assertSelectorExists('select[name="product[category]"]');
            self::assertSelectorExists('input[name="product[taxRate]"][value="20.00"]');
            self::assertSelectorExists('input[name="product[priceTaxIncluded]"][disabled]');
            self::assertSelectorTextContains('textarea[name="product[ingredients]"]', 'Eau, sucre, arôme naturel.');
            self::assertSelectorExists('input[name="product[coverImageUrl]"]');
            self::assertSelectorExists('form.admin-danger-zone');

            $client->request('GET', '/admin/products/new?ean=12345678');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Nouveau produit');
            self::assertSelectorExists('form.admin-product-form');
            self::assertSelectorExists('input[name="product[ean]"][value="12345678"]');
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
