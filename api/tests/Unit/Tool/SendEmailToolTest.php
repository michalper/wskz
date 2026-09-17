<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool;

use App\Dto\Department;
use App\Dto\RoutingOutcome;
use App\Tool\SendEmailTool;
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
        $this->assertSame(['it@example.com'], array_map(
            static fn ($address): string => $address->getAddress(),
            $sentEmail->getTo(),
        ));
        $this->assertSame(['jan.nowak@example.com'], array_map(
            static fn ($address): string => $address->getAddress(),
            $sentEmail->getReplyTo(),
        ));
        $this->assertSame(Department::It->value, $outcome->departmentEmail);
        $this->assertSame('Broken computer', $outcome->subject);
        $this->assertStringContainsString('it@example.com', $result);
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
}
