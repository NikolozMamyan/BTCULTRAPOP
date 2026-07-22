<?php

namespace App\Service;

use App\Entity\ShippingSettings;
use App\Repository\ShippingSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ShippingSettingsManager
{
    public function __construct(
        private ShippingSettingsRepository $settings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSettings(): ShippingSettings
    {
        $settings = $this->settings->findCurrent();

        if ($settings instanceof ShippingSettings) {
            return $settings;
        }

        $settings = new ShippingSettings();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function save(ShippingSettings $settings): void
    {
        $settings->normalizeTierPositions();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
