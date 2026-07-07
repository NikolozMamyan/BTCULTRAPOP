<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MerchantCenterControllerTest extends WebTestCase
{
    public function testMerchantCenterFeedExposesValidStorefrontProducts(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => 'ultrapop.com',
            'HTTPS' => 'on',
        ]);
        $this->skipIfDatabaseIsUnavailable();

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $parentCategoryName = sprintf('Merchant Root %s', $suffix);
        $categoryName = sprintf('Merchant Snacks %s', $suffix);
        $licenseName = sprintf('Merchant License %s', $suffix);
        $promoReference = sprintf('MERCHANT-PROMO-%s', $suffix);
        $normalReference = sprintf('MERCHANT-NORMAL-%s', $suffix);
        $disabledReference = sprintf('MERCHANT-DISABLED-%s', $suffix);
        $noImageReference = sprintf('MERCHANT-NOIMAGE-%s', $suffix);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $parentCategory = (new Category())
            ->setName($parentCategoryName)
            ->setActive(true);
        $category = (new Category())
            ->setName($categoryName)
            ->setParent($parentCategory)
            ->setActive(true);
        $license = (new License())
            ->setName($licenseName)
            ->setActive(true);

        $promoProduct = $this->createProduct(
            $promoReference,
            sprintf('Merchant Promo & Spécial <Ninja> %s', $suffix),
            $category,
            $license,
            5,
        )
            ->setDescription('<p>Boisson & snack <strong>collector</strong> "test" pour Google.</p>')
            ->setEan('3770015056008')
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setPromoPriceTaxIncluded('9.990000');
        $promoProduct
            ->addImage(
                (new ProductImage())
                    ->setPath(sprintf('/uploads/merchant/%s/cover.jpg', strtolower($suffix)))
                    ->setAlt('Cover Merchant')
                    ->setPosition(0)
                    ->setCover(true),
            )
            ->addImage(
                (new ProductImage())
                    ->setPath(sprintf('/uploads/merchant/%s/extra.jpg', strtolower($suffix)))
                    ->setAlt('Extra Merchant')
                    ->setPosition(1),
            );

        $normalProduct = $this->createProduct($normalReference, 'Merchant Normal Product', $category, $license, 0)
            ->setPriceTaxExcluded('2.916667')
            ->setPriceTaxIncluded('3.500000');
        $normalProduct->addImage(
            (new ProductImage())
                ->setPath(sprintf('/uploads/merchant/%s/normal.jpg', strtolower($suffix)))
                ->setAlt('Normal Merchant')
                ->setCover(true),
        );

        $disabledProduct = $this->createProduct($disabledReference, 'Merchant Disabled Product', $category, $license, 3)
            ->setActive(false);
        $disabledProduct->addImage(
            (new ProductImage())
                ->setPath(sprintf('/uploads/merchant/%s/disabled.jpg', strtolower($suffix)))
                ->setAlt('Disabled Merchant')
                ->setCover(true),
        );

        $noImageProduct = $this->createProduct($noImageReference, 'Merchant No Image Product', $category, $license, 2);

        try {
            $entityManager->persist($parentCategory);
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($promoProduct);
            $entityManager->persist($normalProduct);
            $entityManager->persist($disabledProduct);
            $entityManager->persist($noImageProduct);
            $entityManager->flush();

            $client->request('GET', '/merchant-center/products.xml');

            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'application/xml; charset=UTF-8');

            $xml = (string) $client->getResponse()->getContent();
            $document = new \DOMDocument();
            self::assertTrue($document->loadXML($xml), 'The Merchant Center feed must be valid XML.');
            self::assertSame('rss', $document->documentElement?->nodeName);
            self::assertSame('2.0', $document->documentElement?->getAttribute('version'));
            self::assertSame('http://base.google.com/ns/1.0', $document->documentElement?->getAttribute('xmlns:g'));

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');

            $promoItem = $this->itemById($xpath, $promoReference);
            $normalItem = $this->itemById($xpath, $normalReference);

            self::assertNull($this->itemById($xpath, $disabledReference, false));
            self::assertNull($this->itemById($xpath, $noImageReference, false));

            self::assertSame('in_stock', $this->googleText($xpath, $promoItem, 'availability'));
            self::assertSame('out_of_stock', $this->googleText($xpath, $normalItem, 'availability'));
            self::assertStringStartsWith('https://ultrapop.com/boutique/produit/', $this->googleText($xpath, $promoItem, 'link'));
            self::assertStringStartsWith('https://ultrapop.com/uploads/merchant/', $this->googleText($xpath, $promoItem, 'image_link'));
            self::assertSame(1, $xpath->query('g:additional_image_link', $promoItem)->length);

            self::assertSame('12.00 EUR', $this->googleText($xpath, $promoItem, 'price'));
            self::assertSame('9.99 EUR', $this->googleText($xpath, $promoItem, 'sale_price'));
            self::assertSame('3.50 EUR', $this->googleText($xpath, $normalItem, 'price'));
            self::assertNull($this->googleText($xpath, $normalItem, 'sale_price', false));

            self::assertSame('3770015056008', $this->googleText($xpath, $promoItem, 'gtin'));
            self::assertNull($this->googleText($xpath, $normalItem, 'gtin', false));
            self::assertSame('ULTRAPOP', $this->googleText($xpath, $promoItem, 'brand'));
            self::assertSame(sprintf('%s > %s', $parentCategoryName, $categoryName), $this->googleText($xpath, $promoItem, 'product_type'));
            self::assertSame($licenseName, $this->googleText($xpath, $promoItem, 'custom_label_0'));
            self::assertSame($categoryName, $this->googleText($xpath, $promoItem, 'custom_label_1'));

            self::assertStringContainsString('Merchant Promo &amp; Spécial &lt;Ninja&gt;', $xml);
            self::assertStringNotContainsString('<strong>', $this->googleText($xpath, $promoItem, 'description'));
            self::assertSame(0, $xpath->query('//g:shipping')->length);
            self::assertSame(0, $xpath->query('//g:country[text() = "DE" or text() = "BE"]')->length);
            self::assertStringNotContainsString('fr-default-large_default', $xml);
        } finally {
            $this->cleanupFixtures([
                $promoReference,
                $normalReference,
                $disabledReference,
                $noImageReference,
            ], [
                $categoryName,
                $parentCategoryName,
            ], $licenseName);
        }
    }

    private function createProduct(
        string $reference,
        string $name,
        Category $category,
        License $license,
        int $quantity,
    ): Product {
        return (new Product())
            ->setName($name)
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('1.000000')
            ->setPriceTaxIncluded('1.200000')
            ->setTaxRate('20')
            ->setQuantity($quantity)
            ->setActive(true);
    }

    private function itemById(\DOMXPath $xpath, string $id, bool $required = true): ?\DOMElement
    {
        $items = $xpath->query(sprintf('//item[g:id = "%s"]', $id));
        $item = $items->item(0);

        if (!$item instanceof \DOMElement) {
            if ($required) {
                self::fail(sprintf('Expected Merchant Center item "%s" to be present.', $id));
            }

            return null;
        }

        return $item;
    }

    private function googleText(\DOMXPath $xpath, \DOMElement $item, string $field, bool $required = true): ?string
    {
        $nodes = $xpath->query(sprintf('g:%s', $field), $item);
        $node = $nodes->item(0);

        if (null === $node) {
            if ($required) {
                self::fail(sprintf('Expected Merchant Center field "g:%s" to be present.', $field));
            }

            return null;
        }

        return $node->textContent;
    }

    /**
     * @param list<string> $references
     * @param list<string> $categoryNames
     */
    private function cleanupFixtures(array $references, array $categoryNames, string $licenseName): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);

            foreach ($references as $reference) {
                foreach ($connection->fetchFirstColumn('SELECT id FROM product WHERE reference = ?', [$reference]) as $productId) {
                    $connection->delete('product_image', ['product_id' => $productId]);
                }

                $connection->delete('product', ['reference' => $reference]);
            }

            foreach ($categoryNames as $categoryName) {
                $connection->delete('category', ['name' => $categoryName]);
            }

            $connection->delete('product_license', ['name' => $licenseName]);
        } catch (\Throwable) {
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
