<?php

namespace App\Service;

use App\Service\Mailer\SimpleMailerService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AdminCartRecoveryManager
{
    private const ABANDONED_AFTER = '-2 hours';
    private const RESEND_AFTER = '-24 hours';

    public function __construct(
        private Connection $connection,
        private SimpleMailerService $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{email: string}
     *
     * @throws TransportExceptionInterface
     */
    public function sendReminder(int $cartId): array
    {
        $cart = $this->cart($cartId);

        if (null === $cart) {
            throw new \InvalidArgumentException('admin.cart.recovery.flash.not_found');
        }

        $email = trim((string) ($cart['email'] ?? ''));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('admin.cart.recovery.flash.no_email');
        }

        if ((int) $cart['total_quantity'] <= 0) {
            throw new \InvalidArgumentException('admin.cart.recovery.flash.empty');
        }

        $updatedAt = $this->dateFromDatabase($cart['updated_at']);
        $isAbandoned = 'abandoned' === (string) $cart['status'] || ('active' === (string) $cart['status'] && $updatedAt <= new \DateTimeImmutable(self::ABANDONED_AFTER));

        if (!$isAbandoned) {
            throw new \InvalidArgumentException('admin.cart.recovery.flash.not_eligible');
        }

        $lastSentAt = $this->lastSentAt($cartId);

        if (null !== $lastSentAt && $lastSentAt > new \DateTimeImmutable(self::RESEND_AFTER)) {
            throw new \InvalidArgumentException('admin.cart.recovery.flash.already_sent');
        }

        $token = bin2hex(random_bytes(32));
        $cartUrl = $this->urlGenerator->generate('app_front_cart', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $subject = 'Ton panier ULTRAPOP t’attend encore';
        $products = $this->products((string) ($cart['products'] ?? ''));

        $this->mailer->sendTemplateMessage(
            subject: $subject,
            htmlTemplate: 'emails/cart_recovery.html.twig',
            context: [
                'customer_name' => trim((string) ($cart['customer_name'] ?? '')) ?: 'Hello',
                'cart_url' => $cartUrl,
                'products' => $products,
                'total' => $this->formatCents((int) $cart['total_cents']),
            ],
            textMessage: sprintf(
                "Ton panier ULTRAPOP t'attend encore.\nTotal : %s\nReprendre le panier : %s",
                $this->formatCents((int) $cart['total_cents']),
                $cartUrl,
            ),
            to: [$email],
        );

        $now = new \DateTimeImmutable();
        $this->connection->insert('cart_recovery', [
            'cart_id' => $cartId,
            'email' => $email,
            'recovery_token' => $token,
            'status' => 'sent',
            'sent_at' => $now->format('Y-m-d H:i:s'),
            'clicked_at' => null,
            'converted_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return ['email' => $email];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cart(int $cartId): ?array
    {
        $row = $this->connection->executeQuery(
            'SELECT
                c.id,
                c.status,
                c.updated_at,
                u.email,
                TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS customer_name,
                COALESCE(SUM(ci.quantity), 0) AS total_quantity,
                COALESCE(SUM(ci.quantity * ci.unit_price_tax_included_cents), 0) AS total_cents,
                GROUP_CONCAT(CONCAT(p.name, \' × \', ci.quantity) ORDER BY ci.updated_at DESC SEPARATOR \'||\') AS products
            FROM cart c
            LEFT JOIN app_user u ON u.id = c.user_id
            LEFT JOIN cart_item ci ON ci.cart_id = c.id
            LEFT JOIN product p ON p.id = ci.product_id
            WHERE c.id = ?
            GROUP BY c.id, c.status, c.updated_at, u.email, u.first_name, u.last_name',
            [$cartId],
        )->fetchAssociative();

        return false === $row ? null : $row;
    }

    private function lastSentAt(int $cartId): ?\DateTimeImmutable
    {
        $value = $this->connection->fetchOne(
            'SELECT MAX(sent_at) FROM cart_recovery WHERE cart_id = ? AND status = ?',
            [$cartId, 'sent'],
        );

        if (false === $value || null === $value || '' === $value) {
            return null;
        }

        return $this->dateFromDatabase($value);
    }

    /**
     * @return list<string>
     */
    private function products(string $products): array
    {
        if ('' === trim($products)) {
            return [];
        }

        return array_slice(array_values(array_filter(explode('||', $products))), 0, 4);
    }

    private function dateFromDatabase(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        return new \DateTimeImmutable((string) $value);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
