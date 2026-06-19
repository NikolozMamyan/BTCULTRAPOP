<?php

namespace App\Service;

use Symfony\Component\Asset\Packages;

final readonly class AssetUrlResolver
{
    public function __construct(private Packages $assets)
    {
    }

    public function resolve(?string $path): ?string
    {
        $path = trim((string) $path);

        if ('' === $path) {
            return null;
        }

        if (preg_match('~^(?:https?:)?//|^data:~i', $path)) {
            return $path;
        }

        return $this->assets->getUrl(ltrim($path, '/'));
    }
}
