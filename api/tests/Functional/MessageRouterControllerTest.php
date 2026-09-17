<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Dto\RoutingOutcome;
use App\Service\MessageRoutingAgent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MessageRouterControllerTest extends WebTestCase
{
    public function testRoutesMessageAndReturnsChosenDepartment(): void
    {
        $client = static::createClient();

        $outcome = new RoutingOutcome();
        $outcome->departmentEmail = 'it@example.com';
        $outcome->subject = 'Broken computer';

        $agent = $this->createMock(MessageRoutingAgent::class);
        $agent->expects($this->once())
            ->method('route')
            ->with('jan.nowak@example.com', 'Nie dziala mi komputer')
            ->willReturn($outcome);

        self::getContainer()->set(MessageRoutingAgent::class, $agent);

        $client->request(
            'POST',
            '/api/v1/route-message',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'jan.nowak@example.com',
                'message' => 'Nie dziala mi komputer',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"department":"it@example.com","subject":"Broken computer"}',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testRejectsInvalidPayload(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/route-message',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'not-an-email', 'message' => ''], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testSwaggerDocsAreExposed(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/docs');

        self::assertResponseIsSuccessful();
    }
}
