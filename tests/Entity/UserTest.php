<?php

namespace App\Tests\Entity;

use App\Entity\Address;
use App\Entity\User;
use App\Entity\UserSession;
use App\Service\UserAddressManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class UserTest extends TestCase
{
    public function testEmailIsNormalizedAndUsedAsIdentifier(): void
    {
        $user = (new User())->setEmail('  CLIENT@Example.COM ');

        self::assertSame('client@example.com', $user->getEmail());
        self::assertSame('client@example.com', $user->getUserIdentifier());
    }

    public function testFullNameIsBuiltFromFirstNameAndLastName(): void
    {
        $user = (new User())
            ->setFirstName(' Nikoloz ')
            ->setLastName(' Ultrapop ');

        self::assertSame('Nikoloz', $user->getFirstName());
        self::assertSame('Ultrapop', $user->getLastName());
        self::assertSame('Nikoloz Ultrapop', $user->getFullName());
    }

    public function testAvatarPathAndLoyaltyPointsAreManagedByUser(): void
    {
        $user = (new User())
            ->setAvatarFilename(' avatar.webp ')
            ->setLoyaltyPoints(-20)
            ->addLoyaltyPoints(12)
            ->addLoyaltyPointsFromPurchaseCents(5990);

        self::assertSame('avatar.webp', $user->getAvatarFilename());
        self::assertSame('uploads/avatars/avatar.webp', $user->getAvatarPath());
        self::assertSame(71, $user->getLoyaltyPoints());

        $user->setAvatarFilename('');

        self::assertNull($user->getAvatarFilename());
        self::assertNull($user->getAvatarPath());
    }

    public function testRoleUserIsAlwaysPresent(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testAddressUsesANameAndSynchronizesTheRelation(): void
    {
        $user = new User();
        $address = (new Address())
            ->setName('Maison')
            ->setStreet('10 rue de Paris')
            ->setPostalCode('75001')
            ->setCity('Paris')
            ->setCountryCode('fr')
            ->setDefaultAddress(true);

        $user->addAddress($address);

        self::assertSame('Maison', $address->getName());
        self::assertSame('FR', $address->getCountryCode());
        self::assertSame($user, $address->getUser());
        self::assertTrue($user->getAddresses()->contains($address));
        self::assertSame($address, $user->getDefaultAddress());

        $user->removeAddress($address);

        self::assertNull($address->getUser());
        self::assertFalse($user->getAddresses()->contains($address));
    }

    public function testAddressNameCanUseTheDefaultDeliveryLabel(): void
    {
        $address = (new Address())->setName('Livraison');

        self::assertSame('Livraison', $address->getName());
    }

    public function testFirstAddressIsReturnedWhenNoDefaultIsDefined(): void
    {
        $address = (new Address())->setName('Travail');
        $user = (new User())->addAddress($address);

        self::assertSame($address, $user->getDefaultAddress());
    }

    public function testUserAddressManagerCreatesUpdatesAndSwitchesDefaultAddress(): void
    {
        $manager = new UserAddressManager(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $user = new User();

        $home = $manager->createAddress($user, [
            'address_name' => ' ',
            'street' => ' 10 rue de Paris ',
            'postal_code' => ' 75001 ',
            'city' => ' Paris ',
            'country_code' => 'fr',
        ]);

        self::assertSame('Livraison', $home->getName());
        self::assertSame('10 rue de Paris', $home->getStreet());
        self::assertSame('75001', $home->getPostalCode());
        self::assertSame('Paris', $home->getCity());
        self::assertSame('FR', $home->getCountryCode());
        self::assertTrue($home->isDefaultAddress());
        self::assertCount(0, $manager->validate($home));

        $work = $manager->createAddress($user, [
            'address_name' => 'Bureau',
            'street' => '20 avenue Test',
            'postal_code' => '69001',
            'city' => 'Lyon',
            'country_code' => 'FR',
            'default_address' => '1',
        ]);

        self::assertFalse($home->isDefaultAddress());
        self::assertTrue($work->isDefaultAddress());

        $manager->updateAddress($user, $home, [
            'address_name' => 'Maison',
            'street' => '11 rue Modifiée',
            'postal_code' => '75002',
            'city' => 'Paris',
            'country_code' => 'FR',
        ]);

        self::assertSame('Maison', $home->getName());
        self::assertSame('11 rue Modifiée', $home->getStreet());
        self::assertFalse($home->isDefaultAddress());
        self::assertTrue($work->isDefaultAddress());
        self::assertSame($work, $user->getDefaultAddress());
    }

    public function testUserSessionsAreManagedByTheUser(): void
    {
        $user = new User();
        $session = (new UserSession())
            ->setSelector(str_repeat('a', 32))
            ->setTokenHash(hash('sha256', 'token'));

        $user->addSession($session);

        self::assertSame($user, $session->getUser());
        self::assertTrue($user->getSessions()->contains($session));

        $user->removeSession($session);

        self::assertNull($session->getUser());
        self::assertFalse($user->getSessions()->contains($session));
    }

    public function testUserSessionExpirationAndRevocation(): void
    {
        $now = new \DateTimeImmutable('2026-06-16 10:00:00');
        $session = (new UserSession())
            ->setExpiresAt($now->modify('+1 day'))
            ->setAbsoluteExpiresAt($now->modify('+1 month'));

        self::assertFalse($session->isExpired($now));

        $session->setExpiresAt($now->modify('-1 second'));

        self::assertTrue($session->isExpired($now));
        self::assertFalse($session->isRevoked());

        $session->revoke($now);

        self::assertTrue($session->isRevoked());
        self::assertSame($now, $session->getRevokedAt());
    }
}
