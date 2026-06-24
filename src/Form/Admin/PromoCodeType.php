<?php

namespace App\Form\Admin;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Enum\PromoDiscountType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PromoCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'admin.promo.form.code',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'WELCOME10',
                ],
            ])
            ->add('discountType', ChoiceType::class, [
                'label' => 'admin.promo.form.type',
                'choices' => [
                    'admin.promo.type.percentage' => PromoDiscountType::PERCENTAGE,
                    'admin.promo.type.fixed' => PromoDiscountType::FIXED,
                ],
                'choice_value' => static fn (?PromoDiscountType $type): ?string => $type?->value,
            ])
            ->add('value', NumberType::class, [
                'label' => 'admin.promo.form.value',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0.01,
                    'step' => 0.01,
                ],
            ])
            ->add('validFrom', DateTimeType::class, [
                'label' => 'admin.promo.form.valid_from',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('validUntil', DateTimeType::class, [
                'label' => 'admin.promo.form.valid_until',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('maxUses', IntegerType::class, [
                'label' => 'admin.promo.form.max_uses',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => 'admin.promo.form.unlimited',
                ],
            ])
            ->add('assignedUser', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => sprintf(
                    '%s — %s',
                    $user->getFullName() ?: $user->getEmail(),
                    $user->getEmail(),
                ),
                'label' => 'admin.promo.form.assigned_user',
                'placeholder' => 'admin.promo.form.everyone',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.promo.form.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PromoCode::class,
        ]);
    }
}
