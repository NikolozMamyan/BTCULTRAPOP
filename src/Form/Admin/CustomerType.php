<?php

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'admin.customer.form.first_name',
                'attr' => [
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'admin.customer.form.last_name',
                'attr' => [
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'admin.customer.form.email',
                'attr' => [
                    'autocomplete' => 'email',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'admin.customer.form.phone',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'tel',
                ],
            ])
            ->add('preferredLocale', ChoiceType::class, [
                'label' => 'admin.customer.form.locale',
                'choices' => [
                    'profile.french' => 'fr',
                    'profile.english' => 'en',
                ],
            ])
            ->add('loyaltyPoints', IntegerType::class, [
                'label' => 'admin.customer.form.loyalty_points',
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('verified', CheckboxType::class, [
                'label' => 'admin.customer.form.verified',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.customer.form.active',
                'required' => false,
                'disabled' => $options['is_current_admin'],
            ])
            ->add('adminRole', CheckboxType::class, [
                'label' => 'admin.customer.form.admin_role',
                'mapped' => false,
                'required' => false,
                'data' => $options['admin_role'],
                'disabled' => $options['is_current_admin'],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            foreach (['firstName', 'lastName', 'email', 'phone'] as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = trim((string) $data[$field]);
                }
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'admin_role' => false,
            'is_current_admin' => false,
        ]);

        $resolver->setAllowedTypes('admin_role', 'bool');
        $resolver->setAllowedTypes('is_current_admin', 'bool');
    }
}
