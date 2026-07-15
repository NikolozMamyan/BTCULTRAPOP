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
    private const PRODUCT_IMAGE_FALLBACK = 'img/products/fr-default-large_default.jpg';

    public function __construct(
        private Connection $connection,
        private SimpleMailerService $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private AssetUrlResolver $assetUrlResolver,
        private ProductSlugger $productSlugger,
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
        $products = $this->products($cartId);

        $this->mailer->sendTemplateMessage(
            subject: $subject,
            htmlTemplate: 'emails/cart_recovery.html.twig',
            context: [
                'customer_name' => trim((string) ($cart['customer_name'] ?? '')) ?: 'Hello',
                'cart_url' => $cartUrl,
                'products' => $products,
                'products_count' => (int) $cart['total_quantity'],
                'total' => $this->formatCents((int) $cart['total_cents']),
            ],
            textMessage: sprintf(
                "Ton panier ULTRAPOP t'attend encore.\n%s\nTotal : %s\nReprendre le panier : %s",
                $this->productsTextSummary($products),
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
                COALESCE(SUM(ci.quantity * ci.unit_price_tax_included_cents), 0) AS total_cents
            FROM cart c
            LEFT JOIN app_user u ON u.id = c.user_id
            LEFT JOIN cart_item ci ON ci.cart_id = c.id
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
     * @return list<array{
     *     name: string,
     *     quantity: int,
     *     unit_price: string,
     *     total: string,
     *     image: string|null,
     *     url: string
     * }>
     */
    private function products(int $cartId): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT
                p.id AS product_id,
                p.name,
                ci.quantity,
                ci.unit_price_tax_included_cents,
                (ci.quantity * ci.unit_price_tax_included_cents) AS total_cents,
                pi.path AS image_path
            FROM cart_item ci
            INNER JOIN product p ON p.id = ci.product_id
            LEFT JOIN product_image pi ON pi.id = (
                SELECT pi2.id
                FROM product_image pi2
                WHERE pi2.product_id = p.id
                ORDER BY pi2.cover DESC, pi2.position ASC, pi2.id ASC
                LIMIT 1
            )
            WHERE ci.cart_id = ?
            ORDER BY ci.updated_at DESC, ci.id DESC
            LIMIT 4',
            [$cartId],
        )->fetchAllAssociative();

        return array_map(function (array $row): array {
            $name = (string) $row['name'];
            $imagePath = trim((string) ($row['image_path'] ?? ''));

            return [
                'name' => $name,
                'quantity' => max(1, (int) $row['quantity']),
                'unit_price' => $this->formatCents((int) $row['unit_price_tax_included_cents']),
                'total' => $this->formatCents((int) $row['total_cents']),
                'image' => $this->assetUrlResolver->resolveAbsolute('' !== $imagePath ? $imagePath : self::PRODUCT_IMAGE_FALLBACK),
                'url' => $this->urlGenerator->generate(
                    'app_front_product',
                    [
                        'id' => (int) $row['product_id'],
                        'slug' => $this->productSlugger->slug($name),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ];
        }, $rows);
    }

    /**
     * @param list<array{name: string, quantity: int, total: string}> $products
     */
    private function productsTextSummary(array $products): string
    {
        if ([] === $products) {
            return '';
        }

        return implode("\n", array_map(
            static fn (array $product): string => sprintf('- %s × %d : %s', $product['name'], $product['quantity'], $product['total']),
            $products,
        ));
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
