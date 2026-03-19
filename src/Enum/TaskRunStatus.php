<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Enum;

enum TaskRunStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case RUNNING = 'running';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::RUNNING => 'Running',
            self::SKIPPED => 'Skipped',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::RUNNING => 'warning',
            self::SKIPPED => 'secondary',
        };
    }
}
