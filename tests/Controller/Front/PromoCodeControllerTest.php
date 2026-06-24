<?php

namespace App\Tests\Controller\Front;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\PromoCode;
use App\Enum\PromoDiscountType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PromoCodeControllerTest extends WebTestCase
{
    public function testPublicPromoCodeRecalculatesCart(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $connection = $this->connection();

        if (!$connection->createSchemaManager()->tablesExist(['promo_code'])) {
            self::markTestSkipped('Run Doctrine migrations before testing cart promo codes.');
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $reference = 'PROMO-PRODUCT-' . $suffix;
        $categoryName = 'Promo Category ' . $suffix;
        $licenseName = 'Promo License ' . $suffix;
        $code = 'SAVE10-' . $suffix;
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Promo Product')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(5);
        $promoCode = (new PromoCode())
            ->setCode($code)
            ->setDiscountType(PromoDiscountType::PERCENTAGE)
            ->setValue(10);

        try {
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($product);
            $entityManager->persist($promoCode);
            $entityManager->flush();

            $shop = $client->request('GET', '/boutique');
            $cartToken = $shop->filter('#storefront-app')->attr('data-cart-csrf-value');
            $client->request(
                'POST',
                '/api/cart/items',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_CSRF_TOKEN' => $cartToken,
                ],
                content: json_encode([
                    'productId' => $product->getId(),
                    'quantity' => 1,
                ], \JSON_THROW_ON_ERROR),
            );
            self::assertResponseIsSuccessful();

            $cartPage = $client->request('GET', '/cart');
            $promoToken = $cartPage->filter('form[action="/cart/code-promo"] input[name="_csrf_token"]')->attr('value');
            $client->request(
                'POST',
                '/cart/code-promo',
                [
                    '_csrf_token' => $promoToken,
                    'promo_code' => mb_strtolower($code),
                ],
                server: [
                    'HTTP_ACCEPT' => 'application/json',
                ],
            );

            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame($code, $payload['cart']['promoCode']);
            self::assertSame(120, $payload['cart']['discountCents']);
            self::assertSame('16,80 €', $payload['cart']['totalFormatted']);

            $client->request('GET', '/cart');
            self::assertSelectorTextContains('.cart-promo__active', $code);
            self::assertSelectorTextContains('#cart-page-discount', '-1,20 €');
            self::assertSelectorTextContains('#cart-page-total', '16,80 €');
        } finally {
            $cartCookie = $client->getCookieJar()->get('ultrapop_cart');

            if (null !== $cartCookie) {
                $connection->delete('cart', ['token' => $cartCookie->getValue()]);
            }

            $connection->delete('promo_code', ['code' => $code]);
            $connection->delete('product', ['reference' => $reference]);
            $connection->delete('category', ['name' => $categoryName]);
            $connection->delete('product_license', ['name' => $licenseName]);
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
