<?php

namespace App\Tests\Entity;

use App\Entity\Address;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

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

    public function testFirstAddressIsReturnedWhenNoDefaultIsDefined(): void
    {
        $address = (new Address())->setName('Travail');
        $user = (new User())->addAddress($address);

        self::assertSame($address, $user->getDefaultAddress());
    }
}
