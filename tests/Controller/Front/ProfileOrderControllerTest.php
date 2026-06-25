<?php

namespace App\Tests\Controller\Front;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileOrderControllerTest extends WebTestCase
{
    public function testAuthenticatedProfileOnlyDisplaysTheUsersOrdersAndBlogPlaceholder(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $suffix = bin2hex(random_bytes(4));
        $userEmail = sprintf('profile-orders-%s@example.com', $suffix);
        $otherEmail = sprintf('profile-orders-other-%s@example.com', $suffix);
        $userOrderNumber = sprintf('UP-PROFILE-%s', strtoupper($suffix));
        $otherOrderNumber = sprintf('UP-OTHER-%s', strtoupper($suffix));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $user = (new User())
            ->setEmail($userEmail)
            ->setFirstName('Nina')
            ->setLastName('Profile')
            ->setPassword('test-password-hash');
        $otherUser = (new User())
            ->setEmail($otherEmail)
            ->setFirstName('Other')
            ->setLastName('Customer')
            ->setPassword('test-password-hash');
        $userOrder = $this->createOrder($userOrderNumber, $user, 'Produit du client');
        $otherOrder = $this->createOrder($otherOrderNumber, $otherUser, 'Produit privé');

        try {
            $entityManager->persist($user);
            $entityManager->persist($otherUser);
            $entityManager->persist($userOrder);
            $entityManager->persist($otherOrder);
            $entityManager->flush();
            $client->loginUser($user);

            $client->request('GET', '/profil');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.profile-hub-card--orders[href="#profile-orders"]');
            self::assertSelectorExists('.profile-hub-card--blog[href="/blog"]');
            self::assertSelectorTextContains('.profile-hub-card--blog', 'Blog');
            self::assertSelectorTextContains('#profile-orders', $userOrderNumber);
            self::assertSelectorTextContains('#profile-orders', 'Produit du client');
            self::assertSelectorTextNotContains('#profile-orders', $otherOrderNumber);
            self::assertSelectorTextNotContains('#profile-orders', 'Produit privé');
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->executeStatement(
                'DELETE FROM order_item WHERE order_id IN (SELECT id FROM customer_order WHERE order_number IN (?, ?))',
                [$userOrderNumber, $otherOrderNumber],
            );
            $connection->executeStatement(
                'DELETE FROM customer_order WHERE order_number IN (?, ?)',
                [$userOrderNumber, $otherOrderNumber],
            );
            $connection->executeStatement(
                'DELETE FROM app_user WHERE email IN (?, ?)',
                [$userEmail, $otherEmail],
            );
        }
    }

    private function createOrder(string $number, User $user, string $productName): Order
    {
        $order = (new Order())
            ->setOrderNumber($number)
            ->setUser($user)
            ->setCustomerName($user->getFullName())
            ->setCustomerEmail($user->getEmail())
            ->setShippingName($user->getFullName())
            ->setShippingStreet('13 quai Kléber')
            ->setShippingPostalCode('67000')
            ->setShippingCity('Strasbourg')
            ->setShippingCountryCode('FR');
        $order->addItem(
            (new OrderItem())
                ->setProductName($productName)
                ->setProductReference('PROFILE-TEST')
                ->setProductImage('img/products/fr-default-large_default.jpg')
                ->setQuantity(2)
                ->setUnitPriceTaxExcludedCents(200)
                ->setUnitPriceTaxIncludedCents(240),
        );

        return $order->refreshTotals()->markPaid();
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
