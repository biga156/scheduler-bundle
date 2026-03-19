<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Service;

use Cron\CronExpression;

class CronExpressionParser
{
    public function getNextRunDate(string $expression, ?\DateTimeImmutable $from = null): \DateTimeImmutable
    {
        $cron = new CronExpression($expression);
        $from ??= new \DateTimeImmutable();

        return \DateTimeImmutable::createFromMutable($cron->getNextRunDate($from));
    }

    public function getNextRunDateFromInterval(int $seconds, ?\DateTimeImmutable $lastRun = null): \DateTimeImmutable
    {
        $lastRun ??= new \DateTimeImmutable();

        return $lastRun->modify("+{$seconds} seconds");
    }

    public function isDue(string $expression, ?\DateTimeImmutable $at = null): bool
    {
        $cron = new CronExpression($expression);
        $at ??= new \DateTimeImmutable();

        return $cron->isDue($at);
    }

    public function isIntervalDue(int $seconds, ?\DateTimeImmutable $lastRun, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        if ($lastRun === null) {
            return true;
        }

        $nextRun = $lastRun->modify("+{$seconds} seconds");

        return $now >= $nextRun;
    }

    public function isValidExpression(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }

    public function describe(string $expression): string
    {
        if (!$this->isValidExpression($expression)) {
            return 'Invalid expression';
        }

        $parts = explode(' ', trim($expression));
        if (\count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Common patterns
        if ($expression === '* * * * *') {
            return 'Every minute';
        }
        if ($expression === '*/5 * * * *') {
            return 'Every 5 minutes';
        }
        if ($expression === '*/10 * * * *') {
            return 'Every 10 minutes';
        }
        if ($expression === '*/15 * * * *') {
            return 'Every 15 minutes';
        }
        if ($expression === '*/30 * * * *') {
            return 'Every 30 minutes';
        }
        if ($minute === '0' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every hour';
        }
        if ($hour !== '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return \sprintf('Daily at %s:%s', str_pad($hour, 2, '0', \STR_PAD_LEFT), str_pad($minute, 2, '0', \STR_PAD_LEFT));
        }
        if ($weekday !== '*' && $day === '*' && $month === '*') {
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $dayName = $dayNames[(int) $weekday] ?? $weekday;

            return \sprintf('Weekly on %s at %s:%s', $dayName, str_pad($hour, 2, '0', \STR_PAD_LEFT), str_pad($minute, 2, '0', \STR_PAD_LEFT));
        }

        return $expression;
    }

    public function describeInterval(int $seconds): string
    {
        if ($seconds < 60) {
            return \sprintf('Every %d second%s', $seconds, $seconds !== 1 ? 's' : '');
        }

        if ($seconds < 3600) {
            $minutes = (int) ($seconds / 60);

            return \sprintf('Every %d minute%s', $minutes, $minutes !== 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = (int) ($seconds / 3600);

            return \sprintf('Every %d hour%s', $hours, $hours !== 1 ? 's' : '');
        }

        $days = (int) ($seconds / 86400);

        return \sprintf('Every %d day%s', $days, $days !== 1 ? 's' : '');
    }

    public function calculateNextRun(
        ?string $expression,
        ?int $intervalSeconds,
        ?\DateTimeImmutable $lastRun = null,
    ): ?\DateTimeImmutable {
        if ($expression !== null) {
            return $this->getNextRunDate($expression);
        }

        if ($intervalSeconds !== null) {
            return $this->getNextRunDateFromInterval($intervalSeconds, $lastRun);
        }

        return null;
    }

    public function isTaskDue(
        ?string $expression,
        ?int $intervalSeconds,
        ?\DateTimeImmutable $lastRun,
        ?\DateTimeImmutable $now = null,
    ): bool {
        if ($expression !== null) {
            return $this->isDue($expression, $now);
        }

        if ($intervalSeconds !== null) {
            return $this->isIntervalDue($intervalSeconds, $lastRun, $now);
        }

        return false;
    }
}
