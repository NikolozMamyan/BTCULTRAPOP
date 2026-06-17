<?php

namespace App\Form\Admin;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin.category.form.name',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'admin.category.form.name_placeholder',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'admin.category.form.description',
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'admin.category.form.description_placeholder',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.category.form.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
