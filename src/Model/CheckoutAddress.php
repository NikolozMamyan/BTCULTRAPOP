<?php

namespace App\Model;

use App\Entity\Address;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutAddress
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $street = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $postalCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $city = '';

    #[Assert\NotBlank]
    #[Assert\Country]
    public string $countryCode = 'FR';

    #[Assert\Length(max: 30)]
    public ?string $phone = null;

    public static function fromAddress(Address $address, ?User $user = null): self
    {
        $model = new self();
        $model->name = $user?->getFullName() ?: $address->getName();
        $model->street = $address->getStreet();
        $model->postalCode = $address->getPostalCode();
        $model->city = $address->getCity();
        $model->countryCode = $address->getCountryCode();
        $model->phone = $address->getPhone() ?? $user?->getPhone();

        return $model;
    }

    public static function fromUser(?User $user): self
    {
        if (!$user instanceof User) {
            return new self();
        }

        $defaultAddress = $user->getDefaultAddress();

        if ($defaultAddress instanceof Address) {
            return self::fromAddress($defaultAddress, $user);
        }

        $model = new self();
        $model->name = $user->getFullName();
        $model->phone = $user->getPhone();

        return $model;
    }
}
