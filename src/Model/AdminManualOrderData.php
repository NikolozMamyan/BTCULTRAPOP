<?php

namespace App\Model;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AdminManualOrderData
{
    public ?User $customer = null;

    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Length(max: 30)]
    public string $phone = '';

    #[Assert\Length(max: 255)]
    public string $street = '';

    #[Assert\Length(max: 20)]
    public string $postalCode = '';

    #[Assert\Length(max: 120)]
    public string $city = '';

    #[Assert\NotBlank]
    #[Assert\Country]
    public string $countryCode = 'FR';

    public ?PromoCode $promoCode = null;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000000)]
    public ?int $shippingAmountCents = null;

    public OrderStatus $status = OrderStatus::PENDING_PAYMENT;

    /**
     * @var list<AdminManualOrderItemData>
     */
    #[Assert\Valid]
    #[Assert\Count(
        min: 1,
        max: 50,
        minMessage: 'admin.order.manual.error.item_required',
        maxMessage: 'admin.order.manual.error.too_many_items',
    )]
    public array $items = [];

    #[Assert\Callback]
    public function validateCustomerAndAddress(ExecutionContextInterface $context): void
    {
        $defaultAddress = $this->customer?->getDefaultAddress();
        $resolvedName = trim(sprintf(
            '%s %s',
            '' !== trim($this->firstName) ? $this->firstName : ($this->customer?->getFirstName() ?? ''),
            '' !== trim($this->lastName) ? $this->lastName : ($this->customer?->getLastName() ?? ''),
        ));

        if ('' === $resolvedName) {
            $context->buildViolation('admin.order.manual.error.customer_name_required')
                ->atPath('firstName')
                ->addViolation();
        }

        if ('' === trim($this->email) && '' === ($this->customer?->getEmail() ?? '')) {
            $context->buildViolation('admin.order.manual.error.email_required')
                ->atPath('email')
                ->addViolation();
        }

        foreach ([
            'street' => $defaultAddress?->getStreet(),
            'postalCode' => $defaultAddress?->getPostalCode(),
            'city' => $defaultAddress?->getCity(),
        ] as $field => $fallback) {
            if ('' === trim((string) $this->{$field}) && '' === trim((string) $fallback)) {
                $context->buildViolation('admin.order.manual.error.address_required')
                    ->atPath($field)
                    ->addViolation();
            }
        }
    }
}
