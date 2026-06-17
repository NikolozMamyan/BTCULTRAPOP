<?php

namespace App\Form;

use App\Model\CheckoutAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CheckoutAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'checkout.address.name',
                'attr' => [
                    'autocomplete' => 'shipping name',
                    'placeholder' => 'checkout.address.name_placeholder',
                ],
            ])
            ->add('street', TextType::class, [
                'label' => 'common.street',
                'attr' => [
                    'autocomplete' => 'shipping street-address',
                    'placeholder' => 'checkout.address.street_placeholder',
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'common.postal_code',
                'attr' => [
                    'autocomplete' => 'shipping postal-code',
                    'placeholder' => '75001',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'common.city',
                'attr' => [
                    'autocomplete' => 'shipping address-level2',
                    'placeholder' => 'Paris',
                ],
            ])
            ->add('countryCode', HiddenType::class)
            ->add('phone', TelType::class, [
                'label' => 'common.phone',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'shipping tel',
                    'placeholder' => 'checkout.address.phone_placeholder',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckoutAddress::class,
            'translation_domain' => 'messages',
        ]);
    }
}
