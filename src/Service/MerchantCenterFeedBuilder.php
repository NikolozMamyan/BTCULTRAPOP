<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class MerchantCenterFeedBuilder
{
    private const GOOGLE_NAMESPACE = 'http://base.google.com/ns/1.0';
    private const CHANNEL_TITLE = 'ULTRAPOP';
    private const CHANNEL_LINK = 'https://ultrapop.com/';
    private const CHANNEL_DESCRIPTION = 'Catalogue produits ULTRAPOP pour Google Merchant Center';
    private const BRAND = 'ULTRAPOP';

    public function __construct(
        private ProductRepository $products,
        private ProductSlugger $productSlugger,
        private AssetUrlResolver $assetUrlResolver,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function build(): string
    {
        return $this->buildFromProducts($this->products->findForStorefront());
    }

    /**
     * @param iterable<Product> $products
     */
    public function buildFromProducts(iterable $products): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', self::GOOGLE_NAMESPACE);
        $writer->startElement('channel');
        $writer->writeElement('title', self::CHANNEL_TITLE);
        $writer->writeElement('link', self::CHANNEL_LINK);
        $writer->writeElement('description', self::CHANNEL_DESCRIPTION);

        foreach ($products as $product) {
            $this->writeProduct($writer, $product);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return (string) $writer->outputMemory();
    }

    private function writeProduct(\XMLWriter $writer, Product $product): void
    {
        $coverImage = $product->getCoverImage();
        $imageUrl = $this->imageUrl($coverImage);

        if (null === $imageUrl) {
            $this->logger->warning('Merchant Center feed skipped product without a usable HTTPS cover image.', [
                'product_id' => $product->getId(),
                'reference' => $product->getReference(),
            ]);

            return;
        }

        $writer->startElement('item');
        $writer->writeElement('g:id', $product->getReference());
        $writer->writeElement('g:title', $this->normalize($product->getName()));
        $writer->writeElement('g:description', $this->description($product));
        $writer->writeElement('g:link', $this->productUrl($product));
        $writer->writeElement('g:image_link', $imageUrl);

        foreach ($this->additionalImageUrls($product, $imageUrl) as $additionalImageUrl) {
            $writer->writeElement('g:additional_image_link', $additionalImageUrl);
        }

        $writer->writeElement('g:availability', $product->getQuantity() > 0 ? 'in_stock' : 'out_of_stock');
        $writer->writeElement('g:condition', 'new');
        $writer->writeElement('g:price', $this->price($product->getPriceTaxIncluded()));

        if ($product->hasPromoPrice()) {
            $writer->writeElement('g:sale_price', $this->price((string) $product->getPromoPriceTaxIncluded()));
        }

        $ean = $this->normalize($product->getEan());

        if ('' !== $ean) {
            $writer->writeElement('g:gtin', $ean);
        }

        $writer->writeElement('g:brand', self::BRAND);

        $productType = $this->productType($product);

        if ('' !== $productType) {
            $writer->writeElement('g:product_type', $productType);
        }

        $license = $this->normalize($product->getLicense()?->getName());

        if ('' !== $license) {
            $writer->writeElement('g:custom_label_0', $license);
        }

        $category = $this->normalize($product->getCategory()?->getName());

        if ('' !== $category) {
            $writer->writeElement('g:custom_label_1', $category);
        }

        $writer->endElement();
    }

    private function description(Product $product): string
    {
        $seoDescription = $this->normalize($product->getSeoDescription());

        if ('' !== $seoDescription) {
            return $seoDescription;
        }

        $description = $this->normalize(strip_tags(html_entity_decode(
            (string) $product->getDescription(),
            \ENT_QUOTES | \ENT_HTML5,
            'UTF-8',
        )));

        if ('' !== $description) {
            return $description;
        }

        return $this->normalize($product->getName());
    }

    private function productUrl(Product $product): string
    {
        return $this->urlGenerator->generate(
            'app_front_product',
            $this->productSlugger->routeParameters($product),
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function imageUrl(?ProductImage $image): ?string
    {
        $path = trim((string) $image?->getPath());

        if ('' === $path) {
            return null;
        }

        $url = $this->assetUrlResolver->resolveAbsolute($path);

        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            return null;
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function additionalImageUrls(Product $product, string $coverImageUrl): array
    {
        $urls = [];
        $seen = [$coverImageUrl => true];

        foreach ($product->getImages() as $image) {
            $url = $this->imageUrl($image);

            if (null === $url || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $urls[] = $url;
        }

        return $urls;
    }

    private function productType(Product $product): string
    {
        $path = array_values(array_filter(
            $product->getCategory()?->getPathNames() ?? [],
            fn (string $name): bool => '' !== $this->normalize($name),
        ));

        if ('Tout' === ($path[0] ?? null)) {
            array_shift($path);
        }

        return implode(' > ', array_map($this->normalize(...), $path));
    }

    private function price(string $amount): string
    {
        return number_format((float) str_replace(',', '.', $amount), 2, '.', '') . ' EUR';
    }

    private function normalize(?string $value): string
    {
        $value = html_entity_decode(trim((string) $value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
