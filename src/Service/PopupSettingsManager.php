<?php

namespace App\Service;

use App\Entity\PopupSettings;
use App\Repository\PopupSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PopupSettingsManager
{
    public function __construct(
        private PopupSettingsRepository $settings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSettings(): PopupSettings
    {
        $settings = $this->settings->findCurrent();

        if ($settings instanceof PopupSettings) {
            return $settings;
        }

        $settings = new PopupSettings();
        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function save(PopupSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
