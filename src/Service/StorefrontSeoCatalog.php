<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StorefrontSeoCatalog
{
    public function __construct(
        private ProductRepository $products,
        private ProductSlugger $productSlugger,
        private AssetUrlResolver $assetUrlResolver,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(SEO_ORIGIN)%')]
        private string $seoOrigin,
    ) {
    }

    /**
     * @return list<array{
     *     name: string,
     *     category: string,
     *     license: string,
     *     description: string,
     *     url: string,
     *     image: ?string,
     *     lastmod: \DateTimeImmutable
     * }>
     */
    public function products(): array
    {
        return array_map(
            fn (Product $product): array => [
                'name' => $product->getName(),
                'category' => $product->getCategory()?->getName() ?? '',
                'license' => $product->getLicense()?->getName() ?? '',
                'description' => $product->getSeoDescription(),
                'url' => $this->absoluteUrl(
                    'app_front_product',
                    $this->productSlugger->routeParameters($product),
                ),
                'image' => $this->absoluteAssetUrl($product->getCoverImage()?->getPath()),
                'lastmod' => $product->getUpdatedAt(),
            ],
            $this->products->findForStorefront(),
        );
    }

    /**
     * @return list<array{url: string, changefreq: string, priority: string}>
     */
    public function publicPages(): array
    {
        return [
            $this->page('app_front_home', 'weekly', '1.0'),
            $this->page('app_front_boutique', 'daily', '0.9'),
            $this->page('app_front_licences', 'weekly', '0.7'),
            $this->page('app_front_soldes', 'daily', '0.7'),
            $this->page('app_front_delivery', 'monthly', '0.5'),
            $this->page('app_front_returns', 'monthly', '0.5'),
            $this->page('app_front_terms', 'monthly', '0.4'),
            $this->page('app_front_legal', 'monthly', '0.4'),
            $this->page('app_front_privacy', 'monthly', '0.4'),
            $this->page('app_front_contact', 'monthly', '0.6'),
        ];
    }

    /**
     * @return array{url: string, changefreq: string, priority: string}
     */
    private function page(string $route, string $changefreq, string $priority): array
    {
        return [
            'url' => $this->absoluteUrl($route),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function absoluteUrl(string $route, array $parameters = []): string
    {
        return rtrim($this->seoOrigin, '/') . $this->urlGenerator->generate(
            $route,
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    private function absoluteAssetUrl(?string $path): ?string
    {
        $url = $this->assetUrlResolver->resolve($path);

        if (null === $url || preg_match('~^https?://~i', $url)) {
            return $url;
        }

        return rtrim($this->seoOrigin, '/') . '/' . ltrim($url, '/');
    }
}
