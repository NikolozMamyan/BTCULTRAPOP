<?php

namespace App\Service;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouterInterface;

final readonly class StorefrontReturnUrlResolver
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function sanitize(?string $target): string
    {
        $target = trim((string) $target);

        if ('' === $target
            || !str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_contains($target, '\\')
            || preg_match('/[\r\n]/', $target)
        ) {
            return '';
        }

        $parts = parse_url($target);

        if (false === $parts || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['port'])) {
            return '';
        }

        $path = $parts['path'] ?? '';

        try {
            $context = clone $this->router->getContext();
            $context->setMethod('GET');
            $parameters = (new UrlMatcher($this->router->getRouteCollection(), $context))->match($path);
        } catch (ResourceNotFoundException|MethodNotAllowedException) {
            return '';
        }

        $route = $parameters['_route'] ?? null;

        if (!is_string($route) || !str_starts_with($route, 'app_front_')) {
            return '';
        }

        $safeTarget = $path;

        if (isset($parts['query']) && '' !== $parts['query']) {
            $safeTarget .= '?'.$parts['query'];
        }

        if (isset($parts['fragment']) && '' !== $parts['fragment']) {
            $safeTarget .= '#'.$parts['fragment'];
        }

        return $safeTarget;
    }
}
