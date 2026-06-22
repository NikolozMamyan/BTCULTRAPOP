<?php

namespace App\Tests\Controller\Front;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FrontNavigationTest extends WebTestCase
{
    #[DataProvider('providePages')]
    public function testFrontPageRendersStorefront(string $path, string $title, string $page): void
    {
        $client = static::createClient();

        if ($this->requiresCatalogDatabase($path)) {
            $this->skipIfStorefrontCatalogIsUnavailable();
        }

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorExists(sprintf('#storefront-app[data-page="%s"]', $page));
        self::assertSelectorExists('#storefront-app[data-controller~="favorites"]');
        self::assertSelectorExists('header');
        self::assertSelectorExists('footer');
        self::assertSelectorExists('#search-modal');
        self::assertSelectorExists('#cart-drawer');
        self::assertSelectorExists('#page-transition-skeleton');
        self::assertSelectorExists('#product-preview');
        self::assertSelectorExists('.mobile-app-nav');
        self::assertSelectorExists('link[rel="stylesheet"][href*="styles/app"]');
    }

    public static function providePages(): iterable
    {
        yield 'home' => ['/', "Entre dans l'univers Pop Culture", 'home'];
        yield 'shop' => ['/boutique', 'Toute la boutique', 'shop'];
        yield 'licences' => ['/licences', 'Licences', 'shop'];
        yield 'sales' => ['/soldes', 'Soldes', 'shop'];
        yield 'product' => ['/boutique/product/1648', 'ULTRA ICE TEA - Vegeta', 'product'];
        yield 'wishlist' => ['/favoris', 'Mes favoris', 'wishlist'];
        yield 'profile' => ['/profil', 'Mon profil', 'profile'];
        yield 'cart' => ['/cart', 'Mon panier', 'cart'];
    }

    public function testProfileAndFavoritesAreSeparatePages(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.profile-auth');
        self::assertSelectorTextContains('.profile-auth__face--login', 'Connexion');
        self::assertSelectorTextContains('.profile-auth__face--register', 'Inscription');
        self::assertSelectorExists('input[name="last_name"]');
        self::assertSelectorExists('input[name="first_name"]');
        self::assertSelectorExists('input[name="address_name"]');
        self::assertSelectorNotExists('input[name="address_name"][required]');
        self::assertSelectorExists('form[action="/auth/login"][method="post"] input[name="_csrf_token"]');
        self::assertSelectorExists('form[action="/auth/register"][method="post"] input[name="_csrf_token"]');
        self::assertSelectorExists('input[name="street"]');
        self::assertSelectorExists('input[name="postal_code"]');
        self::assertSelectorExists('input[name="city"]');
        self::assertSelectorCount(2, '[data-language-locale-param]');

        $client->request('GET', '/favoris');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes favoris');
        self::assertSelectorNotExists('.profile-card');
        self::assertSelectorNotExists('.profile-auth');
    }

    public function testHomeUsesOfficialLicencesAndSingleSpotlight(): void
    {
        $client = static::createClient();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.licenses-showcase', 'Univers officiels');
        self::assertSelectorExists('.home-hero');
        self::assertSelectorCount(2, '.home-hero__image');
        self::assertSelectorCount(2, '.home-hero__overlay');
        self::assertSelectorCount(2, '.home-hero__badge');
        self::assertSelectorCount(2, '.home-hero__arrow');
        self::assertSelectorCount(1, '.licence-spotlight');
        self::assertSelectorExists('.licence-spotlight img[src*="MENU_LICENCE-01"]');
        self::assertSelectorCount(1, '.licenses-carousel');
        self::assertSelectorCount(14, '.license-card');
        self::assertSelectorExists('a.license-card[href*="/licences?license=One"]');
        self::assertSelectorExists('a.license-card[href="/licences?license=Naruto"]');
        self::assertSelectorExists('.home-loyalty');
        self::assertSelectorTextContains('.home-loyalty__formula', '1 €');
        self::assertSelectorTextContains('.home-loyalty__reward', '50');
        self::assertSelectorTextContains('.home-loyalty__discount', '-5%');
        self::assertSelectorExists('.home-loyalty__action[href="/profil"]');
        self::assertSelectorCount(3, '.home-loyalty__benefit');
        self::assertSelectorCount(4, '.home-products-grid .shop-product-card');
        self::assertSelectorTextContains('.home-products-grid', 'Ajouter');
        self::assertSelectorTextSame('.home-products h2', 'Les produits du moment');
        self::assertSelectorNotExists('.home-services');
        self::assertSelectorNotExists('#cd-h');
        self::assertStringNotContainsString('Retour 30 jours', $crawler->html());
        self::assertStringNotContainsString('Goodies & Accessoires', $crawler->html());
    }

    public function testShopDisplaysImportedProducts(): void
    {
        $client = static::createClient();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $crawler = $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(84, '.shop-product-card');
        self::assertSelectorTextContains('.shop-product-card:first-child', 'ULTRA ICE TEA - Vegeta');
        self::assertSelectorExists('.shop-product-card a[href="/boutique/product/1648"]');
        self::assertSelectorExists('.shop-product-card .favorite-button[data-action="favorites#toggle"]');
        self::assertSelectorExists('.shop-product-card__add.cart-add-button[data-action="cart#add"]');
        self::assertSelectorExists('.shop-product-card button[data-action="product-preview-open"] .fa-magnifying-glass-plus');
        self::assertSelectorTextContains('.shop-product-card:first-child', '1,31 €');
        self::assertSelectorCount(3, '.shop-filter-card');
        self::assertSelectorExists('[data-controller="shop-filters"]');
        self::assertSelectorExists('.shop-layout[data-shop-filters-filter-field-value="category"]');
        self::assertGreaterThan(1, $crawler->filter('.shop-category-button')->count());
        self::assertSelectorCount(4, '.shop-category-group');
        self::assertSelectorExists('.shop-category-group[open]');
        self::assertSelectorExists('.shop-category-group__count');
        self::assertSelectorExists('.shop-category-button--child .shop-category-button__count');
        self::assertSelectorExists('.shop-category-button--child[title="Chipsan - Chips de Pommes de terre"]');
        self::assertSelectorExists('.shop-category-img-container--root img[src="https://ultrapop.com/img/cms/Germany.png"]');
        self::assertSelectorExists('.shop-category-img-container img[src="https://ultrapop.com/144-product_main_2x/ultrapop-naruto-chibi-naruto-tropical-33cl.jpg"]');
        self::assertSelectorExists('.shop-category-img-container img[src="https://ultrapop.com/116-default_md/komesan-luffy-one-piece-chips-de-riz-barbecue-60g.jpg"]');
        self::assertSelectorExists('.shop-category-img-container img[src="https://ultrapop.com/60-default_md/yokosan-dragon-ball-super-cereales-miel-350g.jpg"]');
        self::assertSelectorExists('.shop-filter-trigger[aria-controls="shop-filters-modal"]');
        self::assertSelectorExists('.shop-filter-modal__footer');
        self::assertSelectorExists('.shop-sort select');
    }

    public function testLicensesPageFiltersByLicense(): void
    {
        $client = static::createClient();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $crawler = $client->request('GET', '/licences');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.shop-layout[data-shop-filters-filter-field-value="license"]');
        self::assertSelectorTextContains('.shop-filter-title', 'Licences');
        self::assertSelectorExists('.shop-filter-title .fa-clapperboard');
        self::assertSelectorExists('.shop-product-card[data-license]');
        self::assertGreaterThan(1, $crawler->filter('.shop-category-button')->count());
    }

    public function testProductPageDisplaysTheSelectedProduct(): void
    {
        $client = static::createClient();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $client->request('GET', '/boutique/product/1648');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'ULTRA ICE TEA - Vegeta');
        self::assertSelectorTextContains('.product-detail__price', '1,31 €');
        self::assertSelectorExists('.product-detail__visual img[src*="164-large_default"]');
        self::assertSelectorExists('[data-controller="product-detail"]');
        self::assertSelectorExists('.product-detail__primary.cart-add-button[data-action="product-detail#addToCart"]');
        self::assertSelectorExists('.product-detail__secondary.favorite-button[data-action="favorites#toggle"]');
        self::assertSelectorCount(3, '.product-tabs__nav button');
        self::assertSelectorCount(0, '.product-formats');
    }

    public function testCartPageDisplaysTheCartLayout(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cart');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cart-page-items');
        self::assertSelectorExists('.cart-page__summary');
        self::assertSelectorTextContains('.cart-page__summary', 'Résumé');
    }

    public function testUnknownProductReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();
        $client->request('GET', '/boutique/product/999');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('.catalog-empty', 'Ce produit n’est pas disponible');
        self::assertSelectorExists('.catalog-empty__action[href="/boutique"]');
    }

    public function testLocaleCookieRendersTheStorefrontInEnglish(): void
    {
        $client = static::createClient();
        $this->skipIfStorefrontCatalogIsUnavailable();
        $client->getCookieJar()->set(new Cookie('ultrapop_locale', 'en'));

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('h1', 'Enter the Pop Culture universe');
        self::assertSelectorTextContains('header', 'New arrivals');

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My profile');
        self::assertSelectorTextContains('.profile-auth__face--login', 'Sign in');
        self::assertSelectorTextContains('.profile-auth__face--register', 'Registration');
    }

    #[DataProvider('provideEnglishPages')]
    public function testFrontPagesRenderInEnglishFromTheSessionCookie(string $path, string $title): void
    {
        $client = static::createClient();
        $client->getCookieJar()->set(new Cookie('ultrapop_locale', 'en'));

        if ($this->requiresCatalogDatabase($path)) {
            $this->skipIfStorefrontCatalogIsUnavailable();
        }

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('h1', $title);
    }

    public static function provideEnglishPages(): iterable
    {
        yield 'home' => ['/', 'Enter the Pop Culture universe'];
        yield 'shop' => ['/boutique', 'The entire shop'];
        yield 'licenses' => ['/licences', 'Licenses'];
        yield 'sales' => ['/soldes', 'Sales'];
        yield 'product' => ['/boutique/product/1648', 'ULTRA ICE TEA - Vegeta'];
        yield 'favorites' => ['/favoris', 'My favorites'];
        yield 'profile' => ['/profil', 'My profile'];
        yield 'cart' => ['/cart', 'My cart'];
    }

    private function skipIfStorefrontCatalogIsUnavailable(): void
    {
        $this->skipIfDatabaseIsUnavailable();

        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        if ((int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE reference = '28989'") < 1) {
            self::markTestSkipped('Storefront product catalog is not loaded in the test database.');
        }
    }

    private function requiresCatalogDatabase(string $path): bool
    {
        return '/' === $path
            || str_starts_with($path, '/boutique')
            || str_starts_with($path, '/licences')
            || str_starts_with($path, '/soldes');
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
