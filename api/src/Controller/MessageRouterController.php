<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RouteMessageRequest;
use App\Service\MessageRoutingAgent;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MessageRouterController
{
    public function __construct(
        private MessageRoutingAgent $agent,
    ) {
    }

    #[Route('/api/v1/route-message', name: 'route_message', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/route-message',
        summary: 'Classify a user message and route it to the correct department by email',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'message'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jan.nowak@example.com'),
                    new OA\Property(property: 'message', type: 'string', example: 'Nie dziala mi komputer'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message routed to a department mailbox',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'department', type: 'string', example: 'it@example.com'),
                        new OA\Property(property: 'subject', type: 'string', example: 'Broken computer'),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Invalid request payload'),
        ],
    )]
    public function __invoke(#[MapRequestPayload] RouteMessageRequest $payload): JsonResponse
    {
        $outcome = $this->agent->route($payload->email, $payload->message);

        return new JsonResponse([
            'department' => $outcome->departmentEmail,
            'subject' => $outcome->subject,
        ]);
    }
}
