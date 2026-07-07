<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Service\MerchantCenterFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class MerchantCenterFeedBuilderTest extends KernelTestCase
{
    public function testBuildFromProductsGeneratesGoogleMerchantCenterXml(): void
    {
        self::bootKernel();

        $request = Request::create('https://ultrapop.com/');
        $requestStack = static::getContainer()->get(RequestStack::class);
        $router = static::getContainer()->get(RouterInterface::class);
        $builder = static::getContainer()->get(MerchantCenterFeedBuilder::class);
        \assert($requestStack instanceof RequestStack);
        \assert($router instanceof RouterInterface);
        \assert($builder instanceof MerchantCenterFeedBuilder);

        $requestStack->push($request);
        $router->getContext()->fromRequest($request);

        try {
            $categoryParent = (new Category())->setName('Boissons');
            $category = (new Category())->setName('Jus')->setParent($categoryParent);
            $license = (new License())->setName('Naruto');

            $promoProduct = $this->persistedProduct(1686, 'MC-PROMO-1686', 'Produit & Spécial <Ninja>', $category, $license)
                ->setDescription('<p>Boisson & snack <strong>collector</strong> "test".</p>')
                ->setEan('3770015056008')
                ->setQuantity(7)
                ->setPriceTaxIncluded('12.000000')
                ->setPromoPriceTaxIncluded('9.990000');
            $promoProduct
                ->addImage(
                    (new ProductImage())
                        ->setPath('/uploads/merchant/cover.jpg')
                        ->setCover(true)
                        ->setPosition(0),
                )
                ->addImage(
                    (new ProductImage())
                        ->setPath('/uploads/merchant/extra.jpg')
                        ->setPosition(1),
                );

            $outOfStockProduct = $this->persistedProduct(1687, 'MC-NORMAL-1687', 'Produit sans promo', $category, $license)
                ->setQuantity(0)
                ->setPriceTaxIncluded('3.500000');
            $outOfStockProduct->addImage(
                (new ProductImage())
                    ->setPath('/uploads/merchant/normal.jpg')
                    ->setCover(true),
            );

            $productWithoutImage = $this->persistedProduct(1688, 'MC-NOIMAGE-1688', 'Produit sans image', $category, $license)
                ->setQuantity(4);

            $xml = $builder->buildFromProducts([$promoProduct, $outOfStockProduct, $productWithoutImage]);
        } finally {
            $requestStack->pop();
        }

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame('rss', $document->documentElement?->nodeName);
        self::assertSame('2.0', $document->documentElement?->getAttribute('version'));
        self::assertSame('http://base.google.com/ns/1.0', $document->documentElement?->getAttribute('xmlns:g'));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');

        $promoItem = $this->itemById($xpath, 'MC-PROMO-1686');
        $normalItem = $this->itemById($xpath, 'MC-NORMAL-1687');

        self::assertSame(2, $xpath->query('//item')->length);
        self::assertNull($this->itemById($xpath, 'MC-NOIMAGE-1688', false));

        self::assertSame('Produit & Spécial <Ninja>', $this->googleText($xpath, $promoItem, 'title'));
        self::assertSame('Boisson & snack collector "test".', $this->googleText($xpath, $promoItem, 'description'));
        self::assertStringStartsWith('https://ultrapop.com/boutique/produit/1686-', $this->googleText($xpath, $promoItem, 'link'));
        self::assertSame('https://ultrapop.com/uploads/merchant/cover.jpg', $this->googleText($xpath, $promoItem, 'image_link'));
        self::assertSame('https://ultrapop.com/uploads/merchant/extra.jpg', $this->googleText($xpath, $promoItem, 'additional_image_link'));
        self::assertSame('in_stock', $this->googleText($xpath, $promoItem, 'availability'));
        self::assertSame('out_of_stock', $this->googleText($xpath, $normalItem, 'availability'));
        self::assertSame('new', $this->googleText($xpath, $promoItem, 'condition'));
        self::assertSame('12.00 EUR', $this->googleText($xpath, $promoItem, 'price'));
        self::assertSame('9.99 EUR', $this->googleText($xpath, $promoItem, 'sale_price'));
        self::assertSame('3.50 EUR', $this->googleText($xpath, $normalItem, 'price'));
        self::assertNull($this->googleText($xpath, $normalItem, 'sale_price', false));
        self::assertSame('3770015056008', $this->googleText($xpath, $promoItem, 'gtin'));
        self::assertNull($this->googleText($xpath, $normalItem, 'gtin', false));
        self::assertSame('ULTRAPOP', $this->googleText($xpath, $promoItem, 'brand'));
        self::assertSame('Boissons > Jus', $this->googleText($xpath, $promoItem, 'product_type'));
        self::assertSame('Naruto', $this->googleText($xpath, $promoItem, 'custom_label_0'));
        self::assertSame('Jus', $this->googleText($xpath, $promoItem, 'custom_label_1'));
        self::assertStringContainsString('Produit &amp; Spécial &lt;Ninja&gt;', $xml);
        self::assertSame(0, $xpath->query('//g:shipping')->length);
        self::assertSame(0, $xpath->query('//g:country[text() = "DE" or text() = "BE"]')->length);
        self::assertStringNotContainsString('fr-default-large_default', $xml);
    }

    private function persistedProduct(
        int $id,
        string $reference,
        string $name,
        Category $category,
        License $license,
    ): Product {
        $product = (new Product())
            ->setReference($reference)
            ->setName($name)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('1.000000')
            ->setPriceTaxIncluded('1.200000')
            ->setTaxRate('20')
            ->setQuantity(1);

        $idProperty = new \ReflectionProperty(Product::class, 'id');
        $idProperty->setValue($product, $id);

        return $product;
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
}
