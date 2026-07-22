<?php

namespace App\Tests\Model;

use App\Entity\Address;
use App\Entity\Product;
use App\Entity\User;
use App\Model\AdminManualOrderData;
use App\Model\AdminManualOrderItemData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AdminManualOrderDataTest extends TestCase
{
    public function testGuestRequiresIdentityEmailAddressAndProduct(): void
    {
        $data = new AdminManualOrderData();
        $violations = $this->validator()->validate($data);
        $paths = array_map(
            static fn ($violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        );

        self::assertContains('firstName', $paths);
        self::assertContains('email', $paths);
        self::assertContains('street', $paths);
        self::assertContains('postalCode', $paths);
        self::assertContains('city', $paths);
        self::assertContains('items', $paths);
    }

    public function testRegisteredCustomerCanUseSavedIdentityAndAddress(): void
    {
        $user = (new User())
            ->setFirstName('Client')
            ->setLastName('Enregistré')
            ->setEmail('client@example.com');
        $user->addAddress(
            (new Address())
                ->setName('Maison')
                ->setStreet('10 rue Test')
                ->setPostalCode('75001')
                ->setCity('Paris')
                ->setCountryCode('FR')
                ->setDefaultAddress(true),
        );

        $item = new AdminManualOrderItemData();
        $item->product = new Product();
        $data = new AdminManualOrderData();
        $data->customer = $user;
        $data->items = [$item];

        self::assertCount(0, $this->validator()->validate($data));
    }

    private function validator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
