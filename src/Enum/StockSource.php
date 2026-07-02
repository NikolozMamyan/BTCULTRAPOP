<?php

namespace App\Enum;

enum StockSource: string
{
    case BUREAU = 'bureau';
    case CLIC = 'clic';

    public static function default(): self
    {
        return self::BUREAU;
    }

    public static function fromQuery(?string $value): self
    {
        if (null === $value || '' === trim($value)) {
            return self::default();
        }

        return self::tryFrom(trim($value)) ?? self::default();
    }

    public function tableName(): string
    {
        return match ($this) {
            self::BUREAU => 'stock_bureau',
            self::CLIC => 'stock_clic',
        };
    }

    public function labelKey(): string
    {
        return match ($this) {
            self::BUREAU => 'admin.stock.source.bureau',
            self::CLIC => 'admin.stock.source.clic',
        };
    }

    public function allowsEmptyQuantity(): bool
    {
        return self::CLIC === $this;
    }
}
