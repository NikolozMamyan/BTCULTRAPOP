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

final class AdminProductModel3DControllerTest extends WebTestCase
{
    public function testAdminCanConfigureAThreeDimensionalModelForAProductFromItsSubcategory(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-model-3d-%s@example.com', $suffix);
        $password = 'admin-password';
        $parentName = sprintf('Model 3D Parent %s', $suffix);
        $categoryName = sprintf('Model 3D Jus %s', $suffix);
        $licenseName = sprintf('Model 3D License %s', $suffix);
        $productReference = 'MODEL-3D-' . strtoupper($suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Model')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $parent = (new Category())->setName($parentName)->setPosition(999);
        $category = (new Category())->setName($categoryName)->setParent($parent)->setPosition(1);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Model 3D Test Product')
            ->setReference($productReference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('1.000000')
            ->setPriceTaxIncluded('1.200000')
            ->setTaxRate('20')
            ->setQuantity(10);

        try {
            $entityManager->persist($admin);
            $entityManager->persist($parent);
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

            $crawler = $client->request('GET', '/admin/modeles-3d');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.admin-sidebar__sublink.is-active[href="/admin/modeles-3d"]');
            self::assertSelectorExists('.admin-model-3d-card');
            self::assertSelectorExists('.admin-model-3d-product-select');
            self::assertSelectorExists('button[data-action="admin-model-3d#open"]');
            self::assertSelectorExists('form.admin-model-3d-modal__dialog[enctype="multipart/form-data"]');
            self::assertSelectorExists('input[name="texture_image"][type="file"]');

            $option = $crawler
                ->filter(sprintf('option[value="%d"][data-model-action-url="/admin/modeles-3d/produits/%d"]', $product->getId(), $product->getId()));

            self::assertCount(1, $option);
            $token = $option->attr('data-model-token');

            $client->request('POST', sprintf('/admin/modeles-3d/produits/%d', $product->getId()), [
                '_csrf_token' => $token,
                'model_3d' => [
                    'active' => 'on',
                    'modelType' => 'bottle',
                    'widthScale' => '1.08',
                    'height' => '4.08',
                    'bodyBulge' => '0.995',
                    'shoulder' => '1.012',
                    'topCut' => '0.82',
                    'topNeck' => '0.80',
                    'bottomNeck' => '0.81',
                    'lidScale' => '1.00',
                ],
            ]);

            self::assertResponseRedirects('/admin/modeles-3d');

            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $savedModel = $connection->fetchAssociative('SELECT * FROM product_model_3d WHERE product_id = ?', [$product->getId()]);

            self::assertIsArray($savedModel);
            self::assertSame('1', (string) $savedModel['active']);
            self::assertSame('bottle', $savedModel['model_type']);
            self::assertNull($savedModel['texture_path']);
            self::assertSame(1.08, (float) $savedModel['width_scale']);
            self::assertSame(4.08, (float) $savedModel['height']);
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            if (null !== $product->getId()) {
                $connection->delete('product_model_3d', ['product_id' => $product->getId()]);
            }
            $connection->delete('product', ['reference' => $productReference]);
            $connection->delete('category', ['name' => $categoryName]);
            $connection->delete('category', ['name' => $parentName]);
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

            if (!$connection->createSchemaManager()->tablesExist(['product_model_3d'])) {
                self::markTestSkipped('Run Doctrine migrations before testing 3D product models.');
            }
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }
}
