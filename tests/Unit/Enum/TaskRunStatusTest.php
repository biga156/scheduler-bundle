<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Enum;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use PHPUnit\Framework\TestCase;

class TaskRunStatusTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertEquals('success', TaskRunStatus::SUCCESS->value);
        $this->assertEquals('failed', TaskRunStatus::FAILED->value);
        $this->assertEquals('running', TaskRunStatus::RUNNING->value);
        $this->assertEquals('skipped', TaskRunStatus::SKIPPED->value);
    }

    public function testLabels(): void
    {
        $this->assertEquals('Success', TaskRunStatus::SUCCESS->label());
        $this->assertEquals('Failed', TaskRunStatus::FAILED->label());
        $this->assertEquals('Running', TaskRunStatus::RUNNING->label());
        $this->assertEquals('Skipped', TaskRunStatus::SKIPPED->label());
    }

    public function testBadgeClasses(): void
    {
        $this->assertEquals('success', TaskRunStatus::SUCCESS->badgeClass());
        $this->assertEquals('danger', TaskRunStatus::FAILED->badgeClass());
        $this->assertEquals('warning', TaskRunStatus::RUNNING->badgeClass());
        $this->assertEquals('secondary', TaskRunStatus::SKIPPED->badgeClass());
    }

    public function testTryFrom(): void
    {
        $this->assertSame(TaskRunStatus::SUCCESS, TaskRunStatus::tryFrom('success'));
        $this->assertSame(TaskRunStatus::FAILED, TaskRunStatus::tryFrom('failed'));
        $this->assertNull(TaskRunStatus::tryFrom('nonexistent'));
    }
}
