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

final class AdminTaxonomyControllerTest extends WebTestCase
{
    public function testAdminCanOpenCategoryAndLicenseManagementScreens(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-taxonomy-%s@example.com', $suffix);
        $password = 'admin-password';
        $reference = sprintf('TAX-%s', strtoupper($suffix));
        $categoryName = sprintf('Taxonomy Category %s', $suffix);
        $licenseName = sprintf('Taxonomy License %s', $suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Taxonomy')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $category = (new Category())->setName($categoryName)->setDescription('Category test');
        $license = (new License())->setName($licenseName)->setDescription('License test');
        $product = (new Product())
            ->setName('Taxonomy Product Fixture')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(3);

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

            $client->request('GET', '/admin/categories');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Catégories');
            self::assertSelectorExists('details.admin-sidebar__group[open]');
            self::assertSelectorExists('.admin-sidebar__sublink.is-active[href="/admin/categories"]');
            self::assertSelectorTextContains('.admin-category-grid', $categoryName);
            self::assertSelectorExists(sprintf('button[data-admin-products-url-param="/admin/categories/%d/toggle"]', $category->getId()));
            self::assertSelectorExists('a[href="/admin/categories/new"]');

            $client->request('GET', sprintf('/admin/categories/%d/edit', $category->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form.admin-category-form');
            self::assertSelectorExists('input[name="category[name]"]');
            self::assertSelectorExists('input[name="category[active]"]');
            self::assertSelectorNotExists('form.admin-danger-zone');

            $client->request('GET', '/admin/licenses');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Licences');
            self::assertSelectorExists('details.admin-sidebar__group[open]');
            self::assertSelectorExists('.admin-sidebar__sublink.is-active[href="/admin/licenses"]');
            self::assertSelectorTextContains('.admin-category-grid', $licenseName);
            self::assertSelectorExists(sprintf('button[data-admin-products-url-param="/admin/licenses/%d/toggle"]', $license->getId()));
            self::assertSelectorExists('a[href="/admin/licenses/new"]');

            $client->request('GET', sprintf('/admin/licenses/%d/edit', $license->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form.admin-category-form');
            self::assertSelectorExists('input[name="license[name]"]');
            self::assertSelectorExists('input[name="license[active]"]');
            self::assertSelectorNotExists('form.admin-danger-zone');
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
