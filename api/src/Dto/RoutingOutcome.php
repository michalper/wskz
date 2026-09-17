<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Mutable holder shared with SendEmailTool so the controller can tell,
 * after the agent run finishes, whether the tool actually fired.
 */
final class RoutingOutcome
{
    public ?string $departmentEmail = null;
    public ?string $subject = null;
}
