<?php

namespace App\Service;

use App\Exception\SageApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SageApiClient
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(SAGE_BASE_URI)%')]
        private readonly string $baseUri,
        #[Autowire('%env(SAGE_USERNAME)%')]
        private readonly string $username,
        #[Autowire('%env(SAGE_PASSWORD)%')]
        private readonly string $password,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: mixed}
     */
    public function createOrder(array $payload): array
    {
        dd($payload);
        $response = $this->authorizedRequest('POST', '/Order', [
            'json' => $payload,
        ]);

        return [
            'status' => $response->getStatusCode(),
            'body' => $this->decodeResponse($response),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authorizedRequest(string $method, string $path, array $options): ResponseInterface
    {
        $options['headers'] = array_merge([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ], $options['headers'] ?? []);

        $response = $this->request($method, $path, $options, false);

        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            $this->accessToken = null;
            $options['headers']['Authorization'] = 'Bearer ' . $this->getAccessToken();
            $response = $this->request($method, $path, $options, false);
        }

        $this->assertSuccessfulResponse($response, $method, $path);

        return $response;
    }

    private function getAccessToken(): string
    {
        if (null !== $this->accessToken) {
            return $this->accessToken;
        }

        $this->accessToken = $this->authenticate();

        return $this->accessToken;
    }

    private function authenticate(): string
    {
        $this->assertConfigured();

        $response = $this->request('POST', '/Auth/login', [
            'json' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
        ]);

        $token = $this->extractToken($response);

        if ('' === $token) {
            throw new SageApiException('admin.sage_order.error.empty_auth_token');
        }

        return preg_replace('/^Bearer\s+/i', '', $token) ?? $token;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options, bool $throwOnError = true): ResponseInterface
    {
        $options['headers'] = array_merge(
            [
                'Accept' => 'application/json',
            ],
            $options['headers'] ?? [],
        );
        $options['timeout'] ??= 20;

        try {
            $url = $this->url($path);
            $response = $this->httpClient->request($method, $url, $options);

            if ($throwOnError) {
                $this->assertSuccessfulResponse($response, $method, $path);
            }

            return $response;
        } catch (SageApiException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Sage API request crashed.', [
                'method' => $method,
                'path' => $path,
                'url' => $this->url($path),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new SageApiException('admin.sage_order.error.api_unreachable', previous: $exception);
        }
    }

    private function assertSuccessfulResponse(ResponseInterface $response, string $method, string $path): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $url = $this->url($path);
        $message = $this->responseSummary($response);
        $this->logger->warning('Sage API request failed.', [
            'method' => $method,
            'path' => $path,
            'url' => $url,
            'status_code' => $statusCode,
            'response' => $message,
        ]);

        throw new SageApiException(sprintf(
            'Sage a refusé la requête %s %s (HTTP %d) : %s',
            $method,
            $url,
            $statusCode,
            '' !== $message ? $message : 'réponse vide'
        ));
    }

    private function extractToken(ResponseInterface $response): string
    {
        $content = trim($response->getContent(false));
        $decoded = json_decode($content, true);

        if (is_string($decoded)) {
            return trim($decoded);
        }

        if (is_array($decoded)) {
            foreach (['token', 'accessToken', 'access_token', 'jwt', 'bearerToken'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return trim($decoded[$key]);
                }
            }

            foreach (['data', 'result'] as $containerKey) {
                $container = $decoded[$containerKey] ?? null;

                if (!is_array($container)) {
                    continue;
                }

                foreach (['token', 'accessToken', 'access_token', 'jwt', 'bearerToken'] as $key) {
                    if (isset($container[$key]) && is_string($container[$key])) {
                        return trim($container[$key]);
                    }
                }
            }
        }

        if (!str_starts_with($content, '{') && !str_starts_with($content, '[')) {
            return $content;
        }

        return '';
    }

    private function decodeResponse(ResponseInterface $response): mixed
    {
        $content = trim($response->getContent(false));

        if ('' === $content) {
            return null;
        }

        try {
            return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $content;
        }
    }

    private function responseSummary(ResponseInterface $response): string
    {
        $content = trim($response->getContent(false));

        if ('' === $content) {
            return '';
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                foreach (['message', 'error', 'detail', 'title'] as $key) {
                    if (isset($decoded[$key]) && is_string($decoded[$key]) && '' !== trim($decoded[$key])) {
                        return mb_substr(trim($decoded[$key]), 0, 500);
                    }
                }
            }
        } catch (\JsonException) {
        }

        $content = preg_replace('/\s+/u', ' ', $content) ?? $content;

        return mb_substr($content, 0, 500);
    }

    private function url(string $path): string
    {
        return rtrim(trim($this->baseUri), '/') . '/' . ltrim($path, '/');
    }

    private function assertConfigured(): void
    {
        if ('' === trim($this->baseUri) || '' === trim($this->username) || '' === trim($this->password)) {
            throw new SageApiException('admin.sage_order.error.missing_configuration');
        }
    }
}
