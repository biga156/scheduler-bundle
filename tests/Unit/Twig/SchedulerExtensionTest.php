<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Twig;

use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Caeligo\SchedulerBundle\Twig\SchedulerExtension;
use PHPUnit\Framework\TestCase;

class SchedulerExtensionTest extends TestCase
{
    private SchedulerExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SchedulerExtension(new CronExpressionParser());
    }

    public function testGetFiltersReturnsFilters(): void
    {
        $filters = $this->extension->getFilters();
        $filterNames = array_map(fn ($f) => $f->getName(), $filters);

        $this->assertContains('scheduler_describe', $filterNames);
        $this->assertContains('scheduler_badge', $filterNames);
        $this->assertContains('scheduler_duration', $filterNames);
        $this->assertContains('scheduler_ago', $filterNames);
    }

    public function testDescribeWithCronExpression(): void
    {
        $this->assertEquals('Every hour', $this->extension->describe('0 * * * *'));
    }

    public function testDescribeWithInterval(): void
    {
        $this->assertEquals('Every 1 hour', $this->extension->describe(null, 3600));
    }

    public function testDescribeWithNothing(): void
    {
        $this->assertEquals('Not configured', $this->extension->describe());
    }

    public function testBadgeSuccess(): void
    {
        $badge = $this->extension->badge('success');
        $this->assertStringContainsString('bg-success', $badge);
        $this->assertStringContainsString('Success', $badge);
    }

    public function testBadgeFailed(): void
    {
        $badge = $this->extension->badge('failed');
        $this->assertStringContainsString('bg-danger', $badge);
        $this->assertStringContainsString('Failed', $badge);
    }

    public function testBadgeNull(): void
    {
        $badge = $this->extension->badge(null);
        $this->assertStringContainsString('Never run', $badge);
    }

    public function testBadgeUnknownValue(): void
    {
        $badge = $this->extension->badge('unknown');
        $this->assertStringContainsString('bg-secondary', $badge);
        $this->assertStringContainsString('unknown', $badge);
    }

    public function testFormatDurationMilliseconds(): void
    {
        $this->assertEquals('500ms', $this->extension->formatDuration(0.5));
    }

    public function testFormatDurationSeconds(): void
    {
        $this->assertEquals('1.5s', $this->extension->formatDuration(1.5));
    }

    public function testFormatDurationMinutes(): void
    {
        $this->assertEquals('2m 30s', $this->extension->formatDuration(150.0));
    }

    public function testFormatDurationHours(): void
    {
        $this->assertEquals('1h 30m', $this->extension->formatDuration(5400.0));
    }

    public function testFormatDurationNull(): void
    {
        $this->assertEquals('-', $this->extension->formatDuration(null));
    }

    public function testTimeAgoNull(): void
    {
        $this->assertEquals('Never', $this->extension->timeAgo(null));
    }

    public function testTimeAgoJustNow(): void
    {
        $recent = (new \DateTimeImmutable('-10 seconds'))->format(\DateTimeInterface::ATOM);
        $this->assertEquals('Just now', $this->extension->timeAgo($recent));
    }

    public function testTimeAgoMinutes(): void
    {
        $past = (new \DateTimeImmutable('-5 minutes'))->format(\DateTimeInterface::ATOM);
        $result = $this->extension->timeAgo($past);
        $this->assertStringContainsString('minute', $result);
        $this->assertStringContainsString('ago', $result);
    }

    public function testTimeAgoHours(): void
    {
        $past = (new \DateTimeImmutable('-3 hours'))->format(\DateTimeInterface::ATOM);
        $result = $this->extension->timeAgo($past);
        $this->assertStringContainsString('hour', $result);
        $this->assertStringContainsString('ago', $result);
    }

    public function testTimeAgoDays(): void
    {
        $past = (new \DateTimeImmutable('-2 days'))->format(\DateTimeInterface::ATOM);
        $result = $this->extension->timeAgo($past);
        $this->assertStringContainsString('day', $result);
        $this->assertStringContainsString('ago', $result);
    }
}
