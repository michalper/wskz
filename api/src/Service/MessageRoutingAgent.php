<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Department;
use App\Dto\RoutingOutcome;
use App\Tool\SendEmailTool;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

class MessageRoutingAgent
{
    /**
     * Local CPU inference in Ollama is much slower than a hosted LLM API,
     * so the HTTP client needs a generous timeout instead of Guzzle's 60s default.
     */
    private const float OLLAMA_REQUEST_TIMEOUT_SECONDS = 300.0;

    /**
     * Caps how many tokens Ollama may generate per turn. Without this, a small
     * local model can occasionally ramble well past what a tool call or a
     * one-line confirmation needs, turning a fast reply into a multi-minute one.
     */
    private const int OLLAMA_MAX_OUTPUT_TOKENS = 200;

    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'OLLAMA_URL')]
        private readonly string $ollamaUrl,
        #[Autowire(env: 'OLLAMA_MODEL')]
        private readonly string $ollamaModel,
        private readonly ?AIProviderInterface $provider = null,
    ) {
    }

    public function route(string $senderEmail, string $message): RoutingOutcome
    {
        $outcome = new RoutingOutcome();
        $sendEmailTool = new SendEmailTool($this->mailer, $senderEmail, $outcome);

        $agent = Agent::make()
            ->setAiProvider($this->provider ?? new Ollama(
                url: $this->ollamaUrl,
                model: $this->ollamaModel,
                parameters: ['options' => ['num_predict' => self::OLLAMA_MAX_OUTPUT_TOKENS]],
                httpClient: new GuzzleHttpClient(timeout: self::OLLAMA_REQUEST_TIMEOUT_SECONDS),
            ))
            ->setInstructions((string) new SystemPrompt(
                background: [
                    'You are a triage agent. Call the send_email tool to route this message.',
                    'Departments: it@example.com (computer/software/hardware problems), '
                        .'human-resources@example.com (vacation/leave/payroll requests), '
                        .'help-desk@example.com (general support requests), '
                        .'kadry@example.com (Polish HR/personnel matters), '
                        .'other@example.com (anything else).',
                    'After the tool result comes back, reply with one short confirmation sentence.',
                ],
            ))
            ->addTool($sendEmailTool);

        try {
            $agent->chat(new UserMessage($message))->getMessage();
        } catch (\Throwable) {
            // LLM unavailable or failed to call the tool: fall through to the safety-net send below.
        }

        if (null === $outcome->departmentEmail) {
            $sendEmailTool(Department::Other->value, 'Unclassified request', $message);
        }

        return $outcome;
    }
}
