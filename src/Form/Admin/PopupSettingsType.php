<?php

namespace App\Form\Admin;

use App\Entity\PopupSettings;
use App\Entity\PromoCode;
use App\Repository\PromoCodeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PopupSettingsType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('active', CheckboxType::class, [
                'label' => 'admin.popup.form.active',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'admin.popup.form.title',
                'attr' => [
                    'maxlength' => 120,
                    'placeholder' => 'admin.popup.form.title_placeholder',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'admin.popup.form.message',
                'attr' => [
                    'maxlength' => 500,
                    'rows' => 5,
                    'placeholder' => 'admin.popup.form.message_placeholder',
                ],
            ])
            ->add('promoCode', EntityType::class, [
                'class' => PromoCode::class,
                'query_builder' => static fn (PromoCodeRepository $repository) => $repository
                    ->createQueryBuilder('promo')
                    ->andWhere('promo.assignedUser IS NULL')
                    ->orderBy('promo.active', 'DESC')
                    ->addOrderBy('promo.code', 'ASC'),
                'choice_label' => fn (PromoCode $promoCode): string => sprintf(
                    '%s — %s',
                    $promoCode->getCode(),
                    $this->translator->trans($promoCode->appliesToShipping()
                        ? 'admin.promo.application.shipping'
                        : 'admin.promo.application.products'),
                ),
                'label' => 'admin.popup.form.promo_code',
                'placeholder' => 'admin.popup.form.choose_code',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PopupSettings::class,
        ]);
    }
}
