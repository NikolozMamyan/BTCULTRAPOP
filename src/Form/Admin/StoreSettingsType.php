<?php

namespace App\Form\Admin;

use App\Entity\StoreSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class StoreSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('maintenanceEnabled', CheckboxType::class, [
                'label' => 'admin.store_settings.form.enabled',
                'required' => false,
            ])
            ->add('maintenanceStartsAt', DateTimeType::class, [
                'label' => 'admin.store_settings.form.starts_at',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('maintenanceEndsAt', DateTimeType::class, [
                'label' => 'admin.store_settings.form.ends_at',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'Europe/Paris',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StoreSettings::class,
            'csrf_token_id' => 'admin_store_settings',
        ]);
    }
}
