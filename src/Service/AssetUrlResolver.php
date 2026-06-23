<?php

namespace App\Service;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AssetUrlResolver
{
    public function __construct(
        private Packages $assets,
        private RequestStack $requestStack,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $defaultUri,
    ) {
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

    public function resolveAbsolute(?string $path): ?string
    {
        $url = $this->resolve($path);

        if (null === $url || preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $request = $this->requestStack->getCurrentRequest();
        $origin = $request?->getSchemeAndHttpHost() ?? $this->originFromDefaultUri();

        if (null === $origin) {
            return null;
        }

        return rtrim($origin, '/') . '/' . ltrim($url, '/');
    }

    private function originFromDefaultUri(): ?string
    {
        $parts = parse_url(trim($this->defaultUri));

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $port);
    }
}
