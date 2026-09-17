<?php

declare(strict_types=1);

namespace App\Dto;

enum Department: string
{
    case HumanResources = 'human-resources@example.com';
    case HelpDesk = 'help-desk@example.com';
    case It = 'it@example.com';
    case Kadry = 'kadry@example.com';
    case Other = 'other@example.com';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
