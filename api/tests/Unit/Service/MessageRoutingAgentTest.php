<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\Department;
use App\Service\MessageRoutingAgent;
use NeuronAI\Providers\AIProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class MessageRoutingAgentTest extends TestCase
{
    public function testRoutesMessageToDepartmentChosenByTheAgent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $provider = new FakeToolCallingProvider('send_email', [
            'department_email' => Department::It->value,
            'subject' => 'Broken computer',
            'body' => 'Nie dziala mi komputer',
        ]);

        $agent = new MessageRoutingAgent($mailer, 'http://unused:11434/api', 'unused', $provider);

        $outcome = $agent->route('jan.nowak@example.com', 'Nie dziala mi komputer');

        $this->assertSame(Department::It->value, $outcome->departmentEmail);
        $this->assertSame('Broken computer', $outcome->subject);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedDepartmentEmails(): iterable
    {
        foreach (Department::values() as $email) {
            yield $email => [$email];
        }
    }

    /**
     * Deterministic proof, independent of any real LLM's behavior, that once
     * the agent's tool is called with a given department address, the agent
     * routes to exactly that address for every one of the 5 allowed emails.
     * A real Ollama model choosing the *right* department for a given message
     * is a separate, inherently non-deterministic concern -- see e2e.yml,
     * which can only assert the response is *one of* the 5 allowed addresses.
     */
    #[DataProvider('allowedDepartmentEmails')]
    public function testRoutesToEveryAllowedDepartmentWhenToolIsCalledWithIt(string $departmentEmail): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $provider = new FakeToolCallingProvider('send_email', [
            'department_email' => $departmentEmail,
            'subject' => 'Subject',
            'body' => 'Body',
        ]);

        $agent = new MessageRoutingAgent($mailer, 'http://unused:11434/api', 'unused', $provider);

        $outcome = $agent->route('jan.nowak@example.com', 'irrelevant for this test');

        $this->assertSame($departmentEmail, $outcome->departmentEmail);
    }

    public function testFallsBackToOtherWhenProviderFailsToCallTheTool(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $provider = $this->createStub(AIProviderInterface::class);
        $provider->method('systemPrompt')->willReturn($provider);
        $provider->method('setTools')->willReturn($provider);
        $provider->method('chat')->willThrowException(new \RuntimeException('LLM unreachable'));

        $agent = new MessageRoutingAgent($mailer, 'http://unused:11434/api', 'unused', $provider);

        $outcome = $agent->route('jan.nowak@example.com', 'random gibberish');

        $this->assertSame(Department::Other->value, $outcome->departmentEmail);
    }
}
