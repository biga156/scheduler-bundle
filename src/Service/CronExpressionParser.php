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

        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Every minute
        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every minute';
        }

        // Every N minutes: */N * * * *
        if (str_starts_with($minute, '*/') && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            $n = (int) substr($minute, 2);

            return \sprintf('Every %d minute%s', $n, $n !== 1 ? 's' : '');
        }

        // Every hour at :MM
        if (is_numeric($minute) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return $minute === '0' ? 'Every hour' : \sprintf('Every hour at :%s', str_pad($minute, 2, '0', \STR_PAD_LEFT));
        }

        // Every N hours: 0 */N * * *
        if (is_numeric($minute) && str_starts_with($hour, '*/') && $day === '*' && $month === '*' && $weekday === '*') {
            $n = (int) substr($hour, 2);

            return \sprintf('Every %d hour%s at :%s', $n, $n !== 1 ? 's' : '', str_pad($minute, 2, '0', \STR_PAD_LEFT));
        }

        $timeStr = \sprintf('%s:%s', str_pad($hour, 2, '0', \STR_PAD_LEFT), str_pad($minute, 2, '0', \STR_PAD_LEFT));

        // Specific weekday(s): M H * * W
        if ($weekday !== '*' && $day === '*' && $month === '*' && is_numeric($hour) && is_numeric($minute)) {
            if (str_contains($weekday, ',')) {
                $names = array_map(fn ($d) => $dayNames[(int) trim($d)] ?? trim($d), explode(',', $weekday));

                return \sprintf('Weekly on %s at %s', implode(', ', $names), $timeStr);
            }
            $dayName = $dayNames[(int) $weekday] ?? $weekday;

            return \sprintf('Weekly on %s at %s', $dayName, $timeStr);
        }

        // Monthly on specific day: M H D * *
        if (is_numeric($day) && $month === '*' && $weekday === '*' && is_numeric($hour) && is_numeric($minute)) {
            $suffix = match ((int) $day % 10) {
                1 => (int) $day === 11 ? 'th' : 'st',
                2 => (int) $day === 12 ? 'th' : 'nd',
                3 => (int) $day === 13 ? 'th' : 'rd',
                default => 'th',
            };

            return \sprintf('Monthly on the %d%s at %s', (int) $day, $suffix, $timeStr);
        }

        // Daily at specific time: M H * * *
        if (is_numeric($hour) && is_numeric($minute) && $day === '*' && $month === '*' && $weekday === '*') {
            return \sprintf('Daily at %s', $timeStr);
        }

        // Yearly: M H D Mo *
        if (is_numeric($month) && is_numeric($day) && is_numeric($hour) && is_numeric($minute) && $weekday === '*') {
            $mo = $monthNames[(int) $month] ?? $month;

            return \sprintf('Yearly on %s %d at %s', $mo, (int) $day, $timeStr);
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
