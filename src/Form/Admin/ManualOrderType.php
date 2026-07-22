<?php

namespace App\Form\Admin;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Model\AdminManualOrderData;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManualOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => sprintf(
                    '%s — %s',
                    $user->getFullName() ?: $user->getEmail(),
                    $user->getEmail(),
                ),
                'choice_attr' => static function (User $user): array {
                    $address = $user->getDefaultAddress();

                    return [
                        'data-first-name' => $user->getFirstName(),
                        'data-last-name' => $user->getLastName(),
                        'data-email' => $user->getEmail(),
                        'data-phone' => $address?->getPhone() ?? $user->getPhone() ?? '',
                        'data-street' => $address?->getStreet() ?? '',
                        'data-postal-code' => $address?->getPostalCode() ?? '',
                        'data-city' => $address?->getCity() ?? '',
                        'data-country-code' => $address?->getCountryCode() ?? 'FR',
                    ];
                },
                'query_builder' => static fn (UserRepository $users) => $users->createQueryBuilder('customer')
                    ->andWhere('customer.active = true')
                    ->orderBy('customer.lastName', 'ASC')
                    ->addOrderBy('customer.firstName', 'ASC')
                    ->setMaxResults(200),
                'label' => 'admin.order.manual.form.customer',
                'placeholder' => 'admin.order.manual.form.guest_customer',
                'required' => false,
                'attr' => [
                    'data-action' => 'change->admin-manual-order#fillCustomer',
                ],
            ])
            ->add('firstName', TextType::class, $this->optionalField('admin.order.manual.form.first_name', 'firstName'))
            ->add('lastName', TextType::class, $this->optionalField('admin.order.manual.form.last_name', 'lastName'))
            ->add('email', EmailType::class, $this->optionalField('common.email', 'email'))
            ->add('phone', TelType::class, $this->optionalField('admin.order.manual.form.phone', 'phone'))
            ->add('street', TextType::class, $this->optionalField('admin.order.manual.form.street', 'street'))
            ->add('postalCode', TextType::class, $this->optionalField('admin.order.manual.form.postal_code', 'postalCode'))
            ->add('city', TextType::class, $this->optionalField('admin.order.manual.form.city', 'city'))
            ->add('countryCode', CountryType::class, [
                'label' => 'admin.order.manual.form.country',
                'preferred_choices' => ['FR', 'BE', 'LU', 'CH'],
                'attr' => ['data-admin-manual-order-target' => 'countryCode'],
            ])
            ->add('promoCode', EntityType::class, [
                'class' => PromoCode::class,
                'choice_label' => static fn (PromoCode $promo): string => $promo->getCode(),
                'label' => 'admin.order.manual.form.promo_code',
                'placeholder' => 'admin.order.manual.form.no_promo_code',
                'required' => false,
            ])
            ->add('shippingAmountCents', MoneyType::class, [
                'label' => 'admin.order.manual.form.shipping_price',
                'required' => false,
                'currency' => 'EUR',
                'input' => 'integer',
                'divisor' => 100,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'admin.order.manual.form.status',
                'choices' => $this->statusChoices(),
                'choice_value' => static fn (?OrderStatus $status): ?string => $status?->value,
            ])
            ->add('items', CollectionType::class, [
                'entry_type' => ManualOrderItemType::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__item__',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminManualOrderData::class,
        ]);
    }

    /**
     * @return array{label: string, required: false, attr: array<string, string>}
     */
    private function optionalField(string $label, string $target): array
    {
        return [
            'label' => $label,
            'required' => false,
            'attr' => ['data-admin-manual-order-target' => $target],
        ];
    }

    /**
     * @return array<string, OrderStatus>
     */
    private function statusChoices(): array
    {
        $choices = [];

        foreach (OrderStatus::cases() as $status) {
            $choices['admin.order.status.' . $status->value] = $status;
        }

        return $choices;
    }
}
