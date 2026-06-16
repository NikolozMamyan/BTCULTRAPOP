<?php

namespace App\Tests\Controller\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FrontNavigationTest extends WebTestCase
{
    #[DataProvider('providePages')]
    public function testFrontPageRendersStorefront(string $path, string $title, string $page): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorExists(sprintf('#storefront-app[data-page="%s"]', $page));
        self::assertSelectorExists('header');
        self::assertSelectorExists('footer');
        self::assertSelectorExists('#search-modal');
        self::assertSelectorExists('#cart-drawer');
        self::assertSelectorExists('.mobile-app-nav');
        self::assertSelectorExists('link[rel="stylesheet"][href*="styles/app"]');
    }

    public static function providePages(): iterable
    {
        yield 'home' => ['/', "Entre dans l'univers Pop Culture", 'home'];
        yield 'shop' => ['/boutique', 'Toute la boutique', 'shop'];
        yield 'licences' => ['/licences', 'Licences', 'shop'];
        yield 'sales' => ['/soldes', 'Soldes', 'shop'];
        yield 'product' => ['/boutique/product/1', 'Figurine Collector Arcane', 'product'];
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
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Licences officielles');
        self::assertSelectorCount(1, '.licence-spotlight');
        self::assertSelectorExists('.licence-spotlight img[src*="MENU_LICENCE-01"]');
        self::assertSelectorCount(1, '.licenses-carousel');
        self::assertSelectorCount(14, '.license-card');
        self::assertStringNotContainsString('Retour 30 jours', $crawler->html());
        self::assertStringNotContainsString('Goodies & Accessoires', $crawler->html());
    }

    public function testShopDisplaysTheTemporaryPhpProducts(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(8, '.shop-product-card');
        self::assertSelectorTextContains('.shop-product-card:first-child', 'Figurine Collector Arcane');
        self::assertSelectorExists('.shop-product-card a[href="/boutique/product/1"]');
        self::assertSelectorTextContains('.shop-product-card:first-child', '59,90 €');
        self::assertSelectorCount(3, '.shop-filter-card');
        self::assertSelectorExists('[data-controller="shop-filters"]');
        self::assertSelectorExists('.shop-filter-trigger[aria-controls="shop-filters-modal"]');
        self::assertSelectorExists('.shop-filter-modal__footer');
        self::assertSelectorExists('.shop-sort select');
    }

    public function testProductPageDisplaysTheSelectedProduct(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boutique/product/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Statuette Premium One Piece');
        self::assertSelectorTextContains('.product-detail__price', '89,90 €');
        self::assertSelectorExists('.product-detail__visual img[src="https://ultrapop.com/img/p/1/6/7/167.jpg"]');
        self::assertSelectorExists('[data-controller="product-detail"]');
        self::assertSelectorCount(3, '.product-tabs__nav button');
        self::assertSelectorCount(0, '.product-formats');
    }

    public function testUnknownProductReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boutique/product/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLocaleCookieRendersTheStorefrontInEnglish(): void
    {
        $client = static::createClient();
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
        yield 'product' => ['/boutique/product/1', 'Figurine Collector Arcane'];
        yield 'favorites' => ['/favoris', 'My favorites'];
        yield 'profile' => ['/profil', 'My profile'];
        yield 'cart' => ['/cart', 'My cart'];
    }
}
