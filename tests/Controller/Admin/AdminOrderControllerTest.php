<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminOrderControllerTest extends WebTestCase
{
    public function testAdminCanOpenOrderScreens(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('admin-order-%s@example.com', $suffix);
        $password = 'admin-password';
        $reference = sprintf('ORDER-%s', strtoupper($suffix));
        $orderNumber = sprintf('UP-20260617-%s', strtoupper(substr($suffix, 0, 6)));
        $categoryName = sprintf('Order Category %s', $suffix);
        $licenseName = sprintf('Order License %s', $suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Order')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Order Product Fixture')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(7);
        $product->addImage(
            (new ProductImage())
                ->setPath('https://ultrapop.com/img/p/1/7/0/170.jpg')
                ->setAlt('Order Product Fixture')
                ->setCover(true),
        );

        $order = (new Order())
            ->setOrderNumber($orderNumber)
            ->setCustomerName('Client Order Fixture')
            ->setCustomerEmail('client-order@example.com')
            ->setShippingName('Client Order Fixture')
            ->setShippingStreet('10 rue Admin')
            ->setShippingPostalCode('75001')
            ->setShippingCity('Paris')
            ->setShippingCountryCode('FR')
            ->setStripeCheckoutSessionId('cs_test_' . $suffix)
            ->setStripePaymentIntentId('pi_test_' . $suffix)
            ->setStripeCustomerId('cus_test_' . $suffix);
        $order->addItem(
            (new OrderItem())
                ->setProduct($product)
                ->setProductName($product->getName())
                ->setProductReference($product->getReference())
                ->setProductImage($product->getCoverImage()?->getPath())
                ->setCategoryName($category->getName())
                ->setLicenseName($license->getName())
                ->setQuantity(2)
                ->setUnitPriceTaxExcludedCents(1000)
                ->setUnitPriceTaxIncludedCents(1200),
        );
        $order->refreshTotals()->markPaid(new \DateTimeImmutable('2026-06-17 12:00:00'));

        try {
            $entityManager->persist($admin);
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($product);
            $entityManager->persist($order);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');

            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/admin/dashboard');

            $client->request('GET', '/admin/orders');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Commandes');
            self::assertSelectorExists('.admin-sidebar__link.is-active[href="/admin/orders"]');
            self::assertSelectorTextContains('.admin-order-table', $orderNumber);
            self::assertSelectorTextContains('.admin-order-table', 'Client Order Fixture');

            $client->request('GET', sprintf('/admin/orders/%d', $order->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $orderNumber);
            self::assertSelectorTextContains('.admin-order-show', 'Client Order Fixture');
            self::assertSelectorTextContains('.admin-order-items', 'Order Product Fixture');
            self::assertSelectorTextContains('.admin-order-info-list', 'pi_test_' . $suffix);
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('order_item', ['product_reference' => $reference]);
            $connection->delete('customer_order', ['order_number' => $orderNumber]);
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
