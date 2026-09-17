<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthController;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testReportsOkWhenOllamaIsReachable(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('request')->willReturn(new Response(200));

        $controller = new HealthController('http://ollama:11434/api', $httpClient);
        $response = $controller();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            '{"status":"ok","ollama":"up"}',
            (string) $response->getContent(),
        );
    }

    public function testReportsDegradedWhenOllamaIsUnreachable(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('request')->willThrowException(
            new ConnectException('Connection refused', new Request('GET', 'http://ollama:11434/api/tags')),
        );

        $controller = new HealthController('http://ollama:11434/api', $httpClient);
        $response = $controller();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            '{"status":"degraded","ollama":"down"}',
            (string) $response->getContent(),
        );
    }
}
