<?php

namespace App\Service;

use App\Entity\PaymentSettings;
use App\Exception\StripeConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StripeConfigProvider
{
    public function __construct(
        private PaymentSettingsManager $paymentSettingsManager,
        #[Autowire('%env(STRIPE_SANDBOX_SECRET_KEY)%')]
        private string $sandboxSecretKey,
        #[Autowire('%env(STRIPE_SANDBOX_WEBHOOK_SECRET)%')]
        private string $sandboxWebhookSecret,
        #[Autowire('%env(STRIPE_LIVE_SECRET_KEY)%')]
        private string $liveSecretKey,
        #[Autowire('%env(STRIPE_LIVE_WEBHOOK_SECRET)%')]
        private string $liveWebhookSecret,
    ) {
    }

    public function mode(): string
    {
        return $this->paymentSettingsManager->getSettings()->getStripeMode();
    }

    public function secretKey(): string
    {
        $key = PaymentSettings::MODE_LIVE === $this->mode() ? $this->liveSecretKey : $this->sandboxSecretKey;

        if ('' === trim($key)) {
            throw new StripeConfigurationException('stripe.error.missing_secret_key');
        }

        return trim($key);
    }

    public function webhookSecret(): string
    {
        $secret = PaymentSettings::MODE_LIVE === $this->mode() ? $this->liveWebhookSecret : $this->sandboxWebhookSecret;

        if ('' === trim($secret)) {
            throw new StripeConfigurationException('stripe.error.missing_webhook_secret');
        }

        return trim($secret);
    }

    public function isConfigured(): bool
    {
        try {
            $this->secretKey();
        } catch (StripeConfigurationException) {
            return false;
        }

        return true;
    }

    public function isWebhookConfigured(): bool
    {
        try {
            $this->webhookSecret();
        } catch (StripeConfigurationException) {
            return false;
        }

        return true;
    }

    /**
     * @return array{sandbox: bool, live: bool}
     */
    public function configuredModes(): array
    {
        return [
            PaymentSettings::MODE_SANDBOX => '' !== trim($this->sandboxSecretKey),
            PaymentSettings::MODE_LIVE => '' !== trim($this->liveSecretKey),
        ];
    }

    /**
     * @return array{sandbox: bool, live: bool}
     */
    public function configuredWebhooks(): array
    {
        return [
            PaymentSettings::MODE_SANDBOX => '' !== trim($this->sandboxWebhookSecret),
            PaymentSettings::MODE_LIVE => '' !== trim($this->liveWebhookSecret),
        ];
    }
}
