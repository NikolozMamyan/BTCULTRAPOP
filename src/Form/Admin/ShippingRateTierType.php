<?php

namespace App\Form\Admin;

use App\Entity\ShippingRateTier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShippingRateTierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('thresholdCents', MoneyType::class, [
                'label' => 'admin.shipping.form.threshold',
                'currency' => 'EUR',
                'input' => 'integer',
                'divisor' => 100,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('shippingAmountCents', MoneyType::class, [
                'label' => 'admin.shipping.form.shipping_price',
                'currency' => 'EUR',
                'input' => 'integer',
                'divisor' => 100,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'inputmode' => 'decimal',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShippingRateTier::class,
        ]);
    }
}
