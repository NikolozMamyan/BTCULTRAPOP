<?php

namespace App\Service;

use App\Entity\User;

final class AdminEmailVariableRenderer
{
    /**
     * @var array<string, string>
     */
    private const DEFINITIONS = [
        'client.name' => 'admin.emailing.variables.client_name',
        'client.firstName' => 'admin.emailing.variables.client_first_name',
        'client.lastName' => 'admin.emailing.variables.client_last_name',
        'client.email' => 'admin.emailing.variables.client_email',
        'client.phone' => 'admin.emailing.variables.client_phone',
        'client.loyaltyPoints' => 'admin.emailing.variables.client_loyalty_points',
        'client.address' => 'admin.emailing.variables.client_address',
        'client.street' => 'admin.emailing.variables.client_street',
        'client.postalCode' => 'admin.emailing.variables.client_postal_code',
        'client.city' => 'admin.emailing.variables.client_city',
        'client.countryCode' => 'admin.emailing.variables.client_country_code',
        'client.locale' => 'admin.emailing.variables.client_locale',
    ];

    /**
     * @return list<array{key: string, label_key: string}>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach (self::DEFINITIONS as $key => $labelKey) {
            $definitions[] = [
                'key' => $key,
                'label_key' => $labelKey,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, string>
     */
    public function previewVariables(): array
    {
        return [
            'client.name' => 'Camille Dupont',
            'client.firstName' => 'Camille',
            'client.lastName' => 'Dupont',
            'client.email' => 'camille@exemple.com',
            'client.phone' => '06 12 34 56 78',
            'client.loyaltyPoints' => '125',
            'client.address' => '10 rue de Paris, 75001 Paris, FR',
            'client.street' => '10 rue de Paris',
            'client.postalCode' => '75001',
            'client.city' => 'Paris',
            'client.countryCode' => 'FR',
            'client.locale' => 'fr',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function variables(?User $user, string $email): array
    {
        $email = mb_strtolower(trim($email));
        $address = $user?->getDefaultAddress();
        $fullAddress = implode(', ', array_filter([
            $address?->getStreet(),
            trim(sprintf('%s %s', $address?->getPostalCode(), $address?->getCity())),
            $address?->getCountryCode(),
        ], static fn (?string $value): bool => '' !== trim((string) $value)));

        return [
            'client.name' => $user?->getFullName() ?: $email,
            'client.firstName' => $user?->getFirstName() ?? 'Client',
            'client.lastName' => $user?->getLastName() ?? '',
            'client.email' => $email,
            'client.phone' => $user?->getPhone() ?? $address?->getPhone() ?? '',
            'client.loyaltyPoints' => (string) ($user?->getLoyaltyPoints() ?? 0),
            'client.address' => $fullAddress,
            'client.street' => $address?->getStreet() ?? '',
            'client.postalCode' => $address?->getPostalCode() ?? '',
            'client.city' => $address?->getCity() ?? '',
            'client.countryCode' => $address?->getCountryCode() ?? '',
            'client.locale' => $user?->getPreferredLocale() ?? 'fr',
        ];
    }

    public function renderHtml(string $content, ?User $user, string $email): string
    {
        return $this->replace($content, array_map(
            static fn (string $value): string => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8',
            ),
            $this->variables($user, $email),
        ));
    }

    public function renderText(string $content, ?User $user, string $email): string
    {
        return $this->replace($content, $this->variables($user, $email));
    }

    public function assertSupportedVariables(string ...$contents): void
    {
        foreach ($contents as $content) {
            preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u', $content, $matches);

            foreach ($matches[1] ?? [] as $variable) {
                if (!array_key_exists(trim($variable), self::DEFINITIONS)) {
                    throw new \InvalidArgumentException('admin.emailing.flash.invalid_variable');
                }
            }
        }
    }

    /**
     * @param array<string, string> $variables
     */
    private function replace(string $content, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/u',
            static function (array $matches) use ($variables): string {
                $key = trim($matches[1]);

                return $variables[$key] ?? $matches[0];
            },
            $content,
        ) ?? $content;
    }
}
