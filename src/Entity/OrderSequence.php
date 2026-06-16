<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class OrderSequence
{
    #[ORM\Id]
    #[ORM\Column(length: 8)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{8}$/')]
    private string $dateKey = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $nextNumber = 1;

    public function getDateKey(): string
    {
        return $this->dateKey;
    }

    public function setDateKey(string $dateKey): self
    {
        $this->dateKey = trim($dateKey);

        return $this;
    }

    public function getNextNumber(): int
    {
        return $this->nextNumber;
    }

    public function setNextNumber(int $nextNumber): self
    {
        $this->nextNumber = max(1, $nextNumber);

        return $this;
    }
}
