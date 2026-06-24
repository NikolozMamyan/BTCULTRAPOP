<?php

namespace App\Form;

use App\Model\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'contact.form.subject',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'off',
                    'maxlength' => 160,
                    'placeholder' => 'contact.form.subject_placeholder',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'contact.form.email',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 180,
                    'placeholder' => 'contact.form.email_placeholder',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 5000,
                    'rows' => 8,
                    'placeholder' => 'contact.form.message_placeholder',
                ],
            ])
            ->add('website', HiddenType::class, [
                'required' => false,
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
            'csrf_token_id' => 'contact_message',
            'translation_domain' => 'messages',
        ]);
    }
}
