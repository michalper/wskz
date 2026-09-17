<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Department;
use PHPUnit\Framework\TestCase;

final class DepartmentTest extends TestCase
{
    public function testValuesListsAllAllowedAddresses(): void
    {
        $this->assertSame([
            'human-resources@example.com',
            'help-desk@example.com',
            'it@example.com',
            'kadry@example.com',
            'other@example.com',
        ], Department::values());
    }

    public function testTryFromUnknownAddressReturnsNull(): void
    {
        $this->assertNull(Department::tryFrom('unknown@example.com'));
    }
}
