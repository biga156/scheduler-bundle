<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Twig;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SchedulerExtension extends AbstractExtension
{
    public function __construct(
        private readonly CronExpressionParser $cronParser,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('scheduler_describe', $this->describe(...)),
            new TwigFilter('scheduler_badge', $this->badge(...), ['is_safe' => ['html']]),
            new TwigFilter('scheduler_duration', $this->formatDuration(...)),
            new TwigFilter('scheduler_ago', $this->timeAgo(...)),
            new TwigFilter('scheduler_exit_code', $this->exitCodeDescription(...)),
        ];
    }

    public function describe(?string $expression = null, ?int $intervalSeconds = null): string
    {
        if ($expression !== null) {
            return $this->cronParser->describe($expression);
        }

        if ($intervalSeconds !== null) {
            return $this->cronParser->describeInterval($intervalSeconds);
        }

        return 'Not configured';
    }

    public function badge(?string $statusValue): string
    {
        if ($statusValue === null) {
            return '<span class="badge bg-secondary">Never run</span>';
        }

        $status = TaskRunStatus::tryFrom($statusValue);
        if ($status === null) {
            return \sprintf('<span class="badge bg-secondary">%s</span>', htmlspecialchars($statusValue, \ENT_QUOTES));
        }

        return \sprintf(
            '<span class="badge bg-%s">%s</span>',
            $status->badgeClass(),
            htmlspecialchars($status->label(), \ENT_QUOTES),
        );
    }

    public function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 1) {
            return \sprintf('%dms', (int) ($seconds * 1000));
        }

        if ($seconds < 60) {
            return \sprintf('%.1fs', $seconds);
        }

        $minutes = (int) ($seconds / 60);
        $remaining = (int) ($seconds % 60);

        if ($minutes < 60) {
            return \sprintf('%dm %ds', $minutes, $remaining);
        }

        $hours = (int) ($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return \sprintf('%dh %dm', $hours, $remainingMinutes);
    }

    public function timeAgo(?string $isoDate): string
    {
        if ($isoDate === null) {
            return 'Never';
        }

        try {
            $date = new \DateTimeImmutable($isoDate);
        } catch (\Exception) {
            return $isoDate;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        if ($diff < 0) {
            return 'In the future';
        }

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $minutes = (int) ($diff / 60);

            return \sprintf('%d minute%s ago', $minutes, $minutes !== 1 ? 's' : '');
        }

        if ($diff < 86400) {
            $hours = (int) ($diff / 3600);

            return \sprintf('%d hour%s ago', $hours, $hours !== 1 ? 's' : '');
        }

        $days = (int) ($diff / 86400);

        return \sprintf('%d day%s ago', $days, $days !== 1 ? 's' : '');
    }

    public function exitCodeDescription(?int $exitCode): string
    {
        if ($exitCode === null) {
            return '';
        }

        return match ($exitCode) {
            0 => 'Success',
            1 => 'General error',
            2 => 'Misuse of shell command / Invalid arguments',
            3 => 'Command could not be executed',
            7 => 'Runtime error (e.g. missing service, failed dependency)',
            126 => 'Command found but not executable (permission denied)',
            127 => 'Command not found',
            128 => 'Invalid exit argument',
            130 => 'Terminated by Ctrl+C (SIGINT)',
            134 => 'Abort signal (SIGABRT)',
            137 => 'Killed (SIGKILL) — likely out of memory',
            139 => 'Segmentation fault (SIGSEGV)',
            143 => 'Terminated (SIGTERM)',
            255 => 'Exit status out of range',
            default => ($exitCode > 128 && $exitCode < 165)
                ? \sprintf('Terminated by signal %d', $exitCode - 128)
                : '',
        };
    }
}
