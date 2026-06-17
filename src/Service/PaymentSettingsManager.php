<?php

namespace App\Service;

use App\Entity\PaymentSettings;
use App\Repository\PaymentSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PaymentSettingsManager
{
    public function __construct(
        private PaymentSettingsRepository $settings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSettings(): PaymentSettings
    {
        $settings = $this->settings->findOneBy([], ['id' => 'ASC']);

        if ($settings instanceof PaymentSettings) {
            return $settings;
        }

        $settings = new PaymentSettings();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function setStripeMode(string $mode): PaymentSettings
    {
        $settings = $this->getSettings();
        $settings->setStripeMode($mode);
        $this->entityManager->flush();

        return $settings;
    }
}
