<?php

namespace App\Tests\Service;

use App\Exception\SageApiException;
use App\Service\SageApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SageApiClientTest extends TestCase
{
    public function testItSendsOrderWithBearerAuthorizationHeader(): void
    {
        $requests = [];
        $orderRequestOptions = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requests, &$orderRequestOptions): MockResponse {
                $requests[] = $method . ' ' . $url;

                if (str_ends_with($url, '/Auth/login')) {
                    return new MockResponse('{"accessToken":"token-test"}', [
                        'response_headers' => ['content-type: application/json'],
                    ]);
                }

                $orderRequestOptions = $options;

                return new MockResponse('{"ok":true}', [
                    'response_headers' => ['content-type: application/json'],
                ]);
            },
            'https://sage.example.test',
        );
        $client = new SageApiClient(
            $httpClient,
            'https://sage.example.test',
            'user',
            'pass',
            new NullLogger(),
        );

        $result = $client->createOrder([
            'numClient' => '9BTOC',
            'orderLines' => [
                [
                    'reference' => '28989',
                    'designation' => 'Product',
                    'prixHT' => 1.0,
                    'quantite' => 1,
                    'quantitePreparee' => 1,
                ],
            ],
        ]);

        self::assertSame([
            'POST https://sage.example.test/Auth/login',
            'POST https://sage.example.test/Order',
        ], $requests);
        self::assertSame(200, $result['status']);
        self::assertStringContainsString(
            'Bearer token-test',
            json_encode($orderRequestOptions, \JSON_THROW_ON_ERROR),
        );
    }

    public function testItExposesSageHttpErrorDetails(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('"token-test"'),
            new MockResponse('{"message":"Article introuvable dans Sage"}', [
                'http_code' => 400,
                'response_headers' => ['content-type: application/json'],
            ]),
        ], 'https://sage.example.test');
        $client = new SageApiClient(
            $httpClient,
            'https://sage.example.test',
            'user',
            'pass',
            new NullLogger(),
        );

        $this->expectException(SageApiException::class);
        $this->expectExceptionMessage('Sage a refusé la requête POST https://sage.example.test/Order (HTTP 400) : Article introuvable dans Sage');

        $client->createOrder([
            'numClient' => '9BTOC',
            'orderLines' => [
                [
                    'reference' => 'UNKNOWN',
                    'designation' => 'Unknown product',
                    'prixHT' => 1.0,
                    'quantite' => 1,
                    'quantitePreparee' => 1,
                ],
            ],
        ]);
    }
}
