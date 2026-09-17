<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(
        #[Autowire(env: 'OLLAMA_URL')]
        private string $ollamaUrl,
        private ?ClientInterface $httpClient = null,
    ) {
    }

    #[Route('/api/v1/health', name: 'health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'Report whether the API and its Ollama dependency are reachable',
        responses: [
            new OA\Response(
                response: 200,
                description: 'API and Ollama are both reachable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'ollama', type: 'string', example: 'up'),
                    ],
                ),
            ),
            new OA\Response(response: 503, description: 'Ollama is unreachable'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $ollamaUp = $this->isOllamaReachable();

        return new JsonResponse(
            [
                'status' => $ollamaUp ? 'ok' : 'degraded',
                'ollama' => $ollamaUp ? 'up' : 'down',
            ],
            $ollamaUp ? 200 : 503,
        );
    }

    private function isOllamaReachable(): bool
    {
        try {
            $response = ($this->httpClient ?? new Client())->request(
                'GET',
                "{$this->ollamaUrl}/tags",
                ['timeout' => 3],
            );
        } catch (GuzzleException) {
            return false;
        }

        return 200 === $response->getStatusCode();
    }
}
