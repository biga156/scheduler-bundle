<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Service;

use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use PHPUnit\Framework\TestCase;

class CronExpressionParserTest extends TestCase
{
    private CronExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CronExpressionParser();
    }

    public function testIsValidExpression(): void
    {
        $this->assertTrue($this->parser->isValidExpression('* * * * *'));
        $this->assertTrue($this->parser->isValidExpression('0 * * * *'));
        $this->assertTrue($this->parser->isValidExpression('*/5 * * * *'));
        $this->assertTrue($this->parser->isValidExpression('0 3 * * 1'));
        $this->assertFalse($this->parser->isValidExpression('invalid'));
        $this->assertFalse($this->parser->isValidExpression(''));
        $this->assertFalse($this->parser->isValidExpression('* * *'));
    }

    public function testIsDueEveryMinute(): void
    {
        $this->assertTrue($this->parser->isDue('* * * * *'));
    }

    public function testGetNextRunDateReturnsDateTimeImmutable(): void
    {
        $next = $this->parser->getNextRunDate('* * * * *');
        $this->assertInstanceOf(\DateTimeImmutable::class, $next);
        $this->assertGreaterThanOrEqual(new \DateTimeImmutable(), $next);
    }

    public function testGetNextRunDateFromInterval(): void
    {
        $lastRun = new \DateTimeImmutable('2026-03-19 10:00:00');
        $next = $this->parser->getNextRunDateFromInterval(3600, $lastRun);

        $this->assertEquals(new \DateTimeImmutable('2026-03-19 11:00:00'), $next);
    }

    public function testIsIntervalDueWhenNeverRun(): void
    {
        $this->assertTrue($this->parser->isIntervalDue(3600, null));
    }

    public function testIsIntervalDueWhenEnoughTimePassed(): void
    {
        $lastRun = new \DateTimeImmutable('-2 hours');
        $this->assertTrue($this->parser->isIntervalDue(3600, $lastRun));
    }

    public function testIsIntervalNotDueWhenNotEnoughTimePassed(): void
    {
        $lastRun = new \DateTimeImmutable('-30 minutes');
        $this->assertFalse($this->parser->isIntervalDue(3600, $lastRun));
    }

    public function testDescribeCommonExpressions(): void
    {
        $this->assertEquals('Every minute', $this->parser->describe('* * * * *'));
        $this->assertEquals('Every 5 minutes', $this->parser->describe('*/5 * * * *'));
        $this->assertEquals('Every 10 minutes', $this->parser->describe('*/10 * * * *'));
        $this->assertEquals('Every 15 minutes', $this->parser->describe('*/15 * * * *'));
        $this->assertEquals('Every 30 minutes', $this->parser->describe('*/30 * * * *'));
        $this->assertEquals('Every hour', $this->parser->describe('0 * * * *'));
        $this->assertEquals('Daily at 03:00', $this->parser->describe('0 3 * * *'));
        $this->assertEquals('Invalid expression', $this->parser->describe('invalid'));
    }

    public function testDescribeWeekly(): void
    {
        $this->assertEquals('Weekly on Mon at 09:30', $this->parser->describe('30 9 * * 1'));
    }

    public function testDescribeInterval(): void
    {
        $this->assertEquals('Every 30 seconds', $this->parser->describeInterval(30));
        $this->assertEquals('Every 5 minutes', $this->parser->describeInterval(300));
        $this->assertEquals('Every 2 hours', $this->parser->describeInterval(7200));
        $this->assertEquals('Every 1 day', $this->parser->describeInterval(86400));
    }

    public function testCalculateNextRunWithCron(): void
    {
        $next = $this->parser->calculateNextRun('0 * * * *', null);
        $this->assertInstanceOf(\DateTimeImmutable::class, $next);
    }

    public function testCalculateNextRunWithInterval(): void
    {
        $lastRun = new \DateTimeImmutable('2026-03-19 10:00:00');
        $next = $this->parser->calculateNextRun(null, 3600, $lastRun);
        $this->assertEquals(new \DateTimeImmutable('2026-03-19 11:00:00'), $next);
    }

    public function testCalculateNextRunWithNoSchedule(): void
    {
        $this->assertNull($this->parser->calculateNextRun(null, null));
    }

    public function testIsTaskDueWithCron(): void
    {
        $this->assertTrue($this->parser->isTaskDue('* * * * *', null, null));
    }

    public function testIsTaskDueWithInterval(): void
    {
        $lastRun = new \DateTimeImmutable('-2 hours');
        $this->assertTrue($this->parser->isTaskDue(null, 3600, $lastRun));
    }

    public function testIsTaskDueWithNoSchedule(): void
    {
        $this->assertFalse($this->parser->isTaskDue(null, null, null));
    }
}
