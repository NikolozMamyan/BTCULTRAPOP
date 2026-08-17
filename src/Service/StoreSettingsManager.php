<?php

namespace App\Service;

use App\Entity\StoreSettings;
use App\Repository\StoreSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StoreSettingsManager
{
    public function __construct(
        private StoreSettingsRepository $settings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSettings(): StoreSettings
    {
        $settings = $this->settings->findCurrent();

        if ($settings instanceof StoreSettings) {
            return $settings;
        }

        $settings = new StoreSettings();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function save(StoreSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
