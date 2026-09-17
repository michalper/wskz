<?php

declare(strict_types=1);

namespace App\Tool;

use App\Dto\Department;
use App\Dto\RoutingOutcome;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendEmailTool extends Tool
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $senderEmail,
        private readonly RoutingOutcome $outcome,
    ) {
        parent::__construct(
            name: 'send_email',
            description: 'Send the user request to the appropriate department mailbox. '
                .'Must be called exactly once, with department_email set to one of the allowed addresses.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'department_email',
                type: PropertyType::STRING,
                description: 'The destination department email address.',
                required: true,
                enum: Department::values(),
            ),
            new ToolProperty(
                name: 'subject',
                type: PropertyType::STRING,
                description: 'A short subject line summarizing the request.',
                required: true,
            ),
            new ToolProperty(
                name: 'body',
                type: PropertyType::STRING,
                description: 'The message body to forward to the department.',
                required: true,
            ),
        ];
    }

    public function __invoke(string $department_email, string $subject, string $body): string
    {
        $department = Department::tryFrom($department_email) ?? Department::Other;

        $email = new Email()
            ->from('router@example.com')
            ->to($department->value)
            ->replyTo($this->senderEmail)
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);

        $this->outcome->departmentEmail = $department->value;
        $this->outcome->subject = $subject;

        return "Email sent to {$department->value}.";
    }
}
