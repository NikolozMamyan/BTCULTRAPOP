<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\User;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserAddressManager
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createAddress(User $user, array $payload): Address
    {
        $address = new Address();
        $user->addAddress($address);

        $this->applyPayload($user, $address, $payload);

        return $address;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateAddress(User $user, Address $address, array $payload): void
    {
        $this->applyPayload($user, $address, $payload);
    }

    public function validate(Address $address): ConstraintViolationListInterface
    {
        return $this->validator->validate($address);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(User $user, Address $address, array $payload): void
    {
        $addressName = trim($this->stringValue($payload, 'address_name'));
        $phone = trim($this->stringValue($payload, 'phone'));
        $countryCode = trim($this->stringValue($payload, 'country_code', 'FR'));

        $address
            ->setName('' === $addressName ? 'Livraison' : $addressName)
            ->setStreet($this->stringValue($payload, 'street'))
            ->setPostalCode($this->stringValue($payload, 'postal_code'))
            ->setCity($this->stringValue($payload, 'city'))
            ->setCountryCode('' === $countryCode ? 'FR' : $countryCode)
            ->setPhone('' === $phone ? null : $phone);

        $this->applyDefaultAddress($user, $address, $this->boolValue($payload, 'default_address'));
    }

    private function applyDefaultAddress(User $user, Address $selectedAddress, bool $requestedDefault): void
    {
        if ($requestedDefault || 1 === $user->getAddresses()->count()) {
            foreach ($user->getAddresses() as $address) {
                $address->setDefaultAddress($address === $selectedAddress);
            }

            return;
        }

        if ($selectedAddress->isDefaultAddress()) {
            $selectedAddress->setDefaultAddress(true);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        if (!is_scalar($value) && null !== $value) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function boolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value) && null !== $value) {
            return false;
        }

        return filter_var((string) $value, \FILTER_VALIDATE_BOOLEAN);
    }
}
