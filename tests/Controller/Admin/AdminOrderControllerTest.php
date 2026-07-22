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
        $manualCustomerEmail = sprintf('manual-order-%s@example.com', $suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $connection = static::getContainer()->get(Connection::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);
        \assert($connection instanceof Connection);

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

            $crawler = $client->request('GET', sprintf('/admin/orders/%d', $order->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $orderNumber);
            self::assertSelectorTextContains('.admin-order-show', 'Client Order Fixture');
            self::assertSelectorTextContains('.admin-order-items', 'Order Product Fixture');
            self::assertSelectorTextContains('.admin-order-show__grid > article:nth-child(4)', 'pi_test_' . $suffix);
            self::assertSelectorExists(sprintf('a[href="/admin/orders/export?order=%d"]', $order->getId()));
            self::assertSelectorExists(sprintf('form[action="/admin/orders/%d/delete"]', $order->getId()));

            $statusForm = $crawler->selectButton('Mettre à jour')->form([
                'order_status[status]' => 'shipped',
            ]);
            $client->submit($statusForm);
            self::assertResponseRedirects(sprintf('/admin/orders/%d', $order->getId()));
            self::assertSame('shipped', $connection->fetchOne(
                'SELECT status FROM customer_order WHERE id = ?',
                [$order->getId()],
            ));

            $crawler = $client->request('GET', sprintf('/admin/orders/%d', $order->getId()));
            $client->submit($crawler->selectButton('Mettre à jour')->form([
                'order_status[status]' => 'cancelled',
            ]));
            self::assertResponseRedirects(sprintf('/admin/orders/%d', $order->getId()));

            $crawler = $client->request('GET', sprintf('/admin/orders/%d', $order->getId()));
            $client->submit($crawler->selectButton('Mettre à jour')->form([
                'order_status[status]' => 'preparation',
            ]));
            self::assertResponseRedirects(sprintf('/admin/orders/%d', $order->getId()));
            self::assertSame(7, (int) $connection->fetchOne(
                'SELECT quantity FROM product WHERE id = ?',
                [$product->getId()],
            ));

            $crawler = $client->request('GET', sprintf('/admin/orders/export?order=%d', $order->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Exporter des commandes');
            self::assertSelectorExists('form.admin-order-export-form[data-turbo="false"]');
            self::assertSelectorCount(1, '.admin-order-export-choice input:checked');
            self::assertSelectorNotExists('#order_export_limit');
            $exportForm = $crawler->selectButton('Télécharger le CSV')->form();
            $client->submit($exportForm);
            self::assertResponseIsSuccessful();
            self::assertStringStartsWith('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
            self::assertStringContainsString(
                'attachment;',
                (string) $client->getResponse()->headers->get('Content-Disposition'),
            );

            $crawler = $client->request('GET', '/admin/orders/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Créer une commande manuellement');
            self::assertSelectorCount(1, '.admin-manual-order-item');
            $manualForm = $crawler->selectButton('Créer la commande')->form([
                'manual_order[firstName]' => 'Client',
                'manual_order[lastName]' => 'Manuel',
                'manual_order[email]' => $manualCustomerEmail,
                'manual_order[phone]' => '0600000000',
                'manual_order[street]' => '20 rue Manuelle',
                'manual_order[postalCode]' => '69001',
                'manual_order[city]' => 'Lyon',
                'manual_order[countryCode]' => 'FR',
                'manual_order[status]' => 'pending_payment',
                'manual_order[items][0][product]' => (string) $product->getId(),
                'manual_order[items][0][quantity]' => '1',
            ]);
            $client->submit($manualForm);
            self::assertResponseRedirects();

            $manualOrder = $connection->fetchAssociative(
                'SELECT id, order_number, status, shipping_amount_tax_included_cents, total_tax_included_cents
                 FROM customer_order WHERE customer_email = ?',
                [$manualCustomerEmail],
            );
            self::assertIsArray($manualOrder);
            self::assertSame('pending_payment', $manualOrder['status']);
            self::assertSame(600, (int) $manualOrder['shipping_amount_tax_included_cents']);
            self::assertSame(1800, (int) $manualOrder['total_tax_included_cents']);

            $crawler = $client->request('GET', sprintf('/admin/orders/%d', $manualOrder['id']));
            $deleteToken = $crawler
                ->filter(sprintf('form[action="/admin/orders/%d/delete"] input[name="_token"]', $manualOrder['id']))
                ->attr('value');
            $client->request('POST', sprintf('/admin/orders/%d/delete', $manualOrder['id']), [
                '_token' => $deleteToken,
            ]);
            self::assertResponseRedirects('/admin/orders');
            self::assertSame(0, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM customer_order WHERE customer_email = ?',
                [$manualCustomerEmail],
            ));
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('customer_order', ['customer_email' => $manualCustomerEmail]);
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
