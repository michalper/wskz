<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool;

use App\Dto\Department;
use App\Dto\RoutingOutcome;
use App\Tool\SendEmailTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SendEmailToolTest extends TestCase
{
    public function testSendsEmailToChosenDepartmentWithReplyTo(): void
    {
        $sentEmail = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (RawMessage $email) use (&$sentEmail): bool {
                $sentEmail = $email;

                return true;
            }));

        $outcome = new RoutingOutcome();
        $tool = new SendEmailTool($mailer, 'jan.nowak@example.com', $outcome);

        $result = $tool('it@example.com', 'Broken computer', 'Nie dziala mi komputer');

        $this->assertInstanceOf(Email::class, $sentEmail);

        $toAddresses = array_map(
            static fn ($address): string => $address->getAddress(),
            $sentEmail->getTo(),
        );
        $this->assertSame(['it@example.com'], $toAddresses);

        $replyToAddresses = array_map(
            static fn ($address): string => $address->getAddress(),
            $sentEmail->getReplyTo(),
        );
        $this->assertSame(['jan.nowak@example.com'], $replyToAddresses);
        $this->assertSame(Department::It->value, $outcome->departmentEmail);
        $this->assertSame('Broken computer', $outcome->subject);
        $this->assertStringContainsString('it@example.com', $result);
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

    #[DataProvider('allowedDepartmentEmails')]
    public function testSendsToEveryAllowedDepartmentEmail(string $departmentEmail): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $sentEmail = null;
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (RawMessage $email) use (&$sentEmail): bool {
                $sentEmail = $email;

                return true;
            }));

        $outcome = new RoutingOutcome();
        $tool = new SendEmailTool($mailer, 'jan.nowak@example.com', $outcome);

        $tool($departmentEmail, 'Subject', 'Body');

        $toAddresses = array_map(
            static fn ($address): string => $address->getAddress(),
            $sentEmail->getTo(),
        );
        $this->assertSame([$departmentEmail], $toAddresses);
        $this->assertSame($departmentEmail, $outcome->departmentEmail);
    }

    public function testFallsBackToOtherDepartmentForUnknownAddress(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $outcome = new RoutingOutcome();
        $tool = new SendEmailTool($mailer, 'jan.nowak@example.com', $outcome);

        $tool('not-a-real-department@example.com', 'Subject', 'Body');

        $this->assertSame(Department::Other->value, $outcome->departmentEmail);
    }

    public function testAllToolPropertiesAreRequired(): void
    {
        $tool = new SendEmailTool(
            $this->createStub(MailerInterface::class),
            'jan.nowak@example.com',
            new RoutingOutcome(),
        );

        $this->assertSame(
            ['department_email', 'subject', 'body'],
            $tool->getRequiredProperties(),
        );
    }
}
