<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\License;
use App\Entity\Product;
use App\Enum\ProductStatus;
use App\Repository\CategoryRepository;
use App\Service\ProductPriceCalculator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly ProductPriceCalculator $priceCalculator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin.product.form.name',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'admin.product.form.name_placeholder',
                ],
            ])
            ->add('reference', TextType::class, [
                'label' => 'admin.product.form.reference',
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'UP-0001',
                ],
            ])
            ->add('ean', TextType::class, [
                'label' => 'admin.product.form.ean',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'inputmode' => 'numeric',
                    'placeholder' => '3760000000000',
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choices' => $this->categories->findAssignable(),
                'choice_label' => static fn (Category $category): string => $category->getPathLabel(),
                'label' => 'admin.product.form.category',
                'placeholder' => 'admin.product.form.choose_category',
            ])
            ->add('license', EntityType::class, [
                'class' => License::class,
                'choice_label' => 'name',
                'label' => 'admin.product.form.license',
                'placeholder' => 'admin.product.form.choose_license',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'admin.product.form.status',
                'choice_translation_domain' => 'messages',
                'choices' => [
                    'admin.product.status.standard' => ProductStatus::STANDARD,
                    'admin.product.status.promo' => ProductStatus::PROMO,
                    'admin.product.status.new' => ProductStatus::NEW,
                    'admin.product.status.bestseller' => ProductStatus::BESTSELLER,
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.product.form.active',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'admin.product.form.description',
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'admin.product.form.description_placeholder',
                ],
            ])
            ->add('ingredients', TextareaType::class, [
                'label' => 'admin.product.form.ingredients',
                'required' => false,
                'attr' => [
                    'rows' => 7,
                    'placeholder' => 'admin.product.form.ingredients_placeholder',
                ],
            ])
            ->add('seoTitle', TextType::class, [
                'label' => 'admin.product.form.seo_title',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'admin.product.form.seo_title_placeholder',
                ],
            ])
            ->add('seoDescription', TextareaType::class, [
                'label' => 'admin.product.form.seo_description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'admin.product.form.seo_description_placeholder',
                ],
            ])
            ->add('priceTaxExcluded', TextType::class, [
                'label' => 'admin.product.form.price_ht',
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('priceTaxIncluded', TextType::class, [
                'label' => 'admin.product.form.price_ttc',
                'disabled' => true,
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('promoPriceTaxIncluded', TextType::class, [
                'label' => 'admin.product.form.promo_price_ttc',
                'required' => false,
                'help' => 'admin.product.form.promo_price_help',
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('taxRate', TextType::class, [
                'label' => 'admin.product.form.tax_rate',
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => '20.00',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'admin.product.form.quantity',
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('width', TextType::class, [
                'label' => 'admin.product.form.width',
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('height', TextType::class, [
                'label' => 'admin.product.form.height',
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('depth', TextType::class, [
                'label' => 'admin.product.form.depth',
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('weight', TextType::class, [
                'label' => 'admin.product.form.weight',
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('coverImageUrl', TextType::class, [
                'label' => 'admin.product.form.cover_image',
                'mapped' => false,
                'required' => false,
                'data' => $options['cover_image_url'],
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'img/products/nom-du-fichier.jpg',
                ],
            ])
            ->add('galleryImages', FileType::class, [
                'label' => 'admin.product.gallery.upload_label',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            foreach (['priceTaxExcluded', 'taxRate'] as $field) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = '' === $value ? '0' : self::normalizeDecimal($value);
            }

            foreach (['promoPriceTaxIncluded', 'width', 'height', 'depth', 'weight'] as $field) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = '' === $value ? null : self::normalizeDecimal($value);
            }

            foreach (['ean', 'description', 'ingredients', 'seoTitle', 'seoDescription', 'coverImageUrl'] as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = trim((string) $data[$field]);
                }
            }

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $product = $event->getData();

            if (!$product instanceof Product) {
                return;
            }

            try {
                $product
                    ->setPriceTaxExcluded($this->priceCalculator->normalizeTaxExcluded($product->getPriceTaxExcluded()))
                    ->setTaxRate($this->priceCalculator->normalizeTaxRate($product->getTaxRate()))
                    ->setPriceTaxIncluded($this->priceCalculator->taxIncluded(
                        $product->getPriceTaxExcluded(),
                        $product->getTaxRate(),
                    ))
                    ->setPromoPriceTaxIncluded(
                        null === $product->getPromoPriceTaxIncluded()
                            ? null
                            : $this->priceCalculator->normalizeTaxIncluded($product->getPromoPriceTaxIncluded()),
                    );
            } catch (\InvalidArgumentException) {
                // Field-level validation keeps the form invalid without turning a bad decimal into a 500.
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'cover_image_url' => null,
        ]);

        $resolver->setAllowedTypes('cover_image_url', ['null', 'string']);
    }

    private static function normalizeDecimal(string $value): string
    {
        return str_replace(',', '.', str_replace(' ', '', $value));
    }
}
