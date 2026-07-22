<?php

namespace App\Form\Admin;

use App\Entity\Order;
use App\Model\AdminOrderExportData;
use App\Repository\OrderRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrderExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('orders', EntityType::class, [
                'class' => Order::class,
                'query_builder' => static fn (OrderRepository $orders) => $orders->createQueryBuilder('orders')
                    ->orderBy('orders.createdAt', 'DESC')
                    ->addOrderBy('orders.id', 'DESC')
                    ->setMaxResults(200),
                'choice_label' => static fn (Order $order): string => sprintf(
                    '%s — %s — %s — %s €',
                    $order->getOrderNumber(),
                    $order->getCustomerName(),
                    $order->getCreatedAt()->format('d/m/Y H:i'),
                    number_format($order->getTotalTaxIncludedCents() / 100, 2, ',', ' '),
                ),
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choice_attr' => static fn (): array => [
                    'data-admin-order-export-target' => 'order',
                    'data-action' => 'change->admin-order-export#updateCount',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminOrderExportData::class,
        ]);
    }
}
