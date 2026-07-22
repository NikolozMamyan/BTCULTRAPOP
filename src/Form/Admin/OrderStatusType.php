<?php

namespace App\Form\Admin;

use App\Enum\OrderStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrderStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];

        foreach (OrderStatus::cases() as $status) {
            $choices['admin.order.status.' . $status->value] = $status;
        }

        $builder->add('status', ChoiceType::class, [
            'label' => 'admin.order.status',
            'choices' => $choices,
            'choice_value' => static fn (?OrderStatus $status): ?string => $status?->value,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
