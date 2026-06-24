<?php

namespace App\Tests\Controller\Front;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Entity\User;
use App\Service\CartManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthControllerTest extends WebTestCase
{
    public function testGuestProfileDisplaysSplitAuthenticationForms(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.split-auth-card');
        self::assertSelectorExists('.split-auth-image img[src*="/assets/img/auth/login_form"]');
        self::assertSelectorExists('form[action="/auth/login"][method="post"] input[name="_csrf_token"]');
        self::assertSelectorExists('form[action="/auth/register"][method="post"] input[name="_csrf_token"]');
        self::assertSelectorExists('form[action="/auth/register"] input[name="first_name"]');
        self::assertSelectorExists('form[action="/auth/register"] input[name="address_name"]');
        self::assertSelectorExists('form[action="/auth/register"] input[name="accept_terms"][required]');
        self::assertSelectorExists('.profile-auth__terms a[href="/conditions-generales-de-vente"]');
        self::assertSelectorExists('.profile-auth__terms a[href="/politique-de-confidentialite"]');
        self::assertSelectorExists('[data-action="profile-auth#showRegister"]');
        self::assertSelectorExists('[data-action="profile-auth#showLogin"]');
    }

    public function testRegularUserIsRedirectedToShopAfterLogin(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $email = sprintf('customer-%s@example.com', bin2hex(random_bytes(4)));
        $password = 'customer-password';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Client')
            ->setLastName('ULTRAPOP');
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');

            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/boutique');
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    public function testRegistrationRequiresExplicitTermsAcceptance(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/profil');
        $token = $crawler->filter('form[action="/auth/register"] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/auth/register', [
            '_csrf_token' => $token,
            'last_name' => 'Sans',
            'first_name' => 'Accord',
            'email' => 'sans-accord@example.com',
            'password' => 'password-secure',
            'street' => '10 rue Test',
            'postal_code' => '75001',
            'city' => 'Paris',
        ]);

        self::assertResponseRedirects('/profil');
        $client->followRedirect();
        self::assertSelectorTextContains('.profile-flash--error', 'conditions générales de vente');
    }

    public function testRegistrationSendsAWelcomeEmail(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $email = sprintf('welcome-%s@example.com', bin2hex(random_bytes(4)));
        $crawler = $client->request('GET', '/profil');
        $token = $crawler->filter('form[action="/auth/register"] input[name="_csrf_token"]')->attr('value');

        try {
            $client->request('POST', '/auth/register', [
                '_csrf_token' => $token,
                'last_name' => 'Welcome',
                'first_name' => 'Nina',
                'email' => $email,
                'password' => 'password-secure',
                'address_name' => 'Maison',
                'street' => '10 rue Test',
                'postal_code' => '75001',
                'city' => 'Paris',
                'accept_terms' => '1',
            ]);

            self::assertResponseRedirects('/profil');
            self::assertEmailCount(1);

            $message = self::getMailerMessage();
            self::assertNotNull($message);
            self::assertEmailHeaderSame($message, 'To', $email);
            self::assertEmailHeaderSame($message, 'Subject', 'Bienvenue dans la communauté ULTRAPOP');
            self::assertEmailHtmlBodyContains($message, 'Bienvenue Nina');
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('app_user', ['email' => $email]);
        }
    }

    public function testSavedAddressIsCollapsedIntoCardOnCartPage(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $email = sprintf('cart-address-%s@example.com', $suffix);
        $password = 'customer-password';
        $reference = sprintf('CART-ADDRESS-%s', strtoupper($suffix));
        $categoryName = sprintf('Cart Address Category %s', $suffix);
        $licenseName = sprintf('Cart Address License %s', $suffix);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $cartManager = static::getContainer()->get(CartManager::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);
        \assert($cartManager instanceof CartManager);

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Client')
            ->setLastName('Adresse');
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->addAddress(
            (new Address())
                ->setName('Maison')
                ->setStreet('12 rue de la Livraison')
                ->setPostalCode('75010')
                ->setCity('Paris')
                ->setCountryCode('FR')
                ->setPhone('0601020304')
                ->setDefaultAddress(true),
        );

        $category = (new Category())->setName($categoryName);
        $license = (new License())->setName($licenseName);
        $product = (new Product())
            ->setName('Cart Address Product')
            ->setReference($reference)
            ->setCategory($category)
            ->setLicense($license)
            ->setPriceTaxExcluded('10.000000')
            ->setPriceTaxIncluded('12.000000')
            ->setQuantity(5);
        $cart = $cartManager->createCart($user, sprintf('cart-address-%s', $suffix));
        $cartManager->addProduct($cart, $product);

        try {
            $entityManager->persist($user);
            $entityManager->persist($category);
            $entityManager->persist($license);
            $entityManager->persist($product);
            $entityManager->persist($cart);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $loginToken = $crawler->filter('form[action="/auth/login"][method="post"] input[name="_csrf_token"]')->attr('value');

            $client->request('POST', '/auth/login', [
                '_csrf_token' => $loginToken,
                'email' => $email,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/boutique');

            $client->request('GET', '/cart');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form.checkout-address-form[data-controller="checkout-address"]');
            self::assertSelectorTextContains('.checkout-address-card', 'Client Adresse');
            self::assertSelectorTextContains('.checkout-address-card', '12 rue de la Livraison');
            self::assertSelectorExists('.checkout-address-card__edit[data-action="checkout-address#edit"]');
            self::assertSelectorExists('#checkout-address-editor[hidden]');
            self::assertSelectorNotExists('input[name="checkout_address[acceptTerms]"]');
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('cart', ['token' => sprintf('cart-address-%s', $suffix)]);
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
