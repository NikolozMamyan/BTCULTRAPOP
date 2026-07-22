<?php

namespace App\Form\Admin;

use App\Entity\Product;
use App\Model\AdminManualOrderItemData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManualOrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'label' => 'admin.order.manual.form.product',
                'placeholder' => 'admin.order.manual.form.choose_product',
                'choice_label' => static fn (Product $product): string => sprintf(
                    '%s — %s — %s €',
                    $product->getName(),
                    $product->getReference(),
                    number_format((float) $product->getEffectivePriceTaxIncluded(), 2, ',', ' '),
                ),
                'choice_attr' => static fn (Product $product): array => [
                    'data-price' => number_format((float) $product->getEffectivePriceTaxIncluded(), 2, '.', ''),
                ],
                'attr' => [
                    'data-action' => 'change->admin-manual-order#refreshTotal',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'admin.order.manual.form.quantity',
                'attr' => [
                    'min' => 1,
                    'max' => 1000,
                    'data-action' => 'input->admin-manual-order#refreshTotal',
                ],
            ])
            ->add('unitPriceTaxIncludedCents', MoneyType::class, [
                'label' => 'admin.order.manual.form.unit_price',
                'required' => false,
                'currency' => 'EUR',
                'input' => 'integer',
                'divisor' => 100,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'data-manual-price' => true,
                    'data-action' => 'input->admin-manual-order#refreshTotal',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminManualOrderItemData::class,
        ]);
    }
}
