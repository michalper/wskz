<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Providers\ToolMapperInterface;
use NeuronAI\Tools\ToolInterface;

/**
 * Minimal fake standing in for a real Ollama call: on the first turn it calls
 * the given tool with the given inputs, then answers plainly on the next turn
 * so the agent loop terminates.
 */
final class FakeToolCallingProvider implements AIProviderInterface
{
    /** @var ToolInterface[] */
    private array $tools = [];

    private bool $called = false;

    public function __construct(
        private readonly string $toolName,
        private readonly array $inputs,
    ) {
    }

    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        return $this;
    }

    public function setTools(array $tools): AIProviderInterface
    {
        $this->tools = $tools;

        return $this;
    }

    public function messageMapper(): MessageMapperInterface
    {
        throw new \RuntimeException('Not implemented in fake.');
    }

    public function toolPayloadMapper(): ToolMapperInterface
    {
        throw new \RuntimeException('Not implemented in fake.');
    }

    public function chat(Message ...$messages): Message
    {
        if ($this->called) {
            return new AssistantMessage('Done.');
        }

        $this->called = true;

        foreach ($this->tools as $tool) {
            if ($tool->getName() === $this->toolName) {
                $tool->setInputs($this->inputs);

                return new ToolCallMessage(null, [$tool]);
            }
        }

        return new AssistantMessage('No matching tool found.');
    }

    public function stream(Message ...$messages): \Generator
    {
        throw new \RuntimeException('Not implemented in fake.');
    }

    public function structured(array|Message $messages, string $class, array $response_schema): Message
    {
        throw new \RuntimeException('Not implemented in fake.');
    }

    public function setHttpClient(HttpClientInterface $client): AIProviderInterface
    {
        return $this;
    }
}
