# Scheduling Guide

How to configure task schedules using cron expressions and simple intervals.

## The `#[AsSchedulableCommand]` Attribute

Place this attribute on any Symfony console command alongside `#[AsCommand]`:

```php
use Caeligo\SchedulerBundle\Attribute\AsSchedulableCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:maintenance:cleanup')]
#[AsSchedulableCommand(
    description: 'Clean up expired sessions and temporary data',
    defaultExpression: '0 3 * * *',
    group: 'maintenance',
)]
class MaintenanceCleanupCommand extends Command
{
    // your execute() logic
}
```

### Attribute Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `description` | `string` | `''` | Human-readable description displayed in the UI |
| `defaultExpression` | `?string` | `null` | Default cron expression (e.g., `0 * * * *`) |
| `defaultInterval` | `?int` | `null` | Default interval in seconds (e.g., `3600` for hourly) |
| `group` | `string` | `'default'` | Group label for UI organization |

> **Note:** You can set either `defaultExpression` or `defaultInterval`, not both. The schedule can be changed later via CLI or dashboard.

## Cron Expressions

Standard 5-field cron expressions are supported via the [`dragonmantank/cron-expression`](https://github.com/dragonmantank/cron-expression) library.

### Format

```
*    *    *    *    *
┬    ┬    ┬    ┬    ┬
│    │    │    │    └── Day of week (0-7, 0 and 7 = Sunday)
│    │    │    └────── Month (1-12)
│    │    └─────────── Day of month (1-31)
│    └──────────────── Hour (0-23)
└───────────────────── Minute (0-59)
```

### Common Examples

| Expression | Description |
|------------|-------------|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `*/15 * * * *` | Every 15 minutes |
| `0 * * * *` | Every hour (at minute 0) |
| `0 */2 * * *` | Every 2 hours |
| `0 3 * * *` | Daily at 03:00 |
| `0 0 * * *` | Daily at midnight |
| `30 9 * * 1-5` | Weekdays at 09:30 |
| `0 3 * * 0` | Every Sunday at 03:00 |
| `0 0 1 * *` | First day of each month at midnight |
| `0 0 1 1 *` | January 1st at midnight (yearly) |

## Simple Intervals

If you prefer interval-based scheduling (e.g., "run every 2 hours"), use `defaultInterval` in seconds:

```php
#[AsSchedulableCommand(
    description: 'Process queue items',
    defaultInterval: 300, // every 5 minutes
    group: 'queue',
)]
```

### Common Intervals

| Seconds | Meaning |
|---------|---------|
| `60` | Every minute |
| `300` | Every 5 minutes |
| `900` | Every 15 minutes |
| `1800` | Every 30 minutes |
| `3600` | Every hour |
| `7200` | Every 2 hours |
| `21600` | Every 6 hours |
| `43200` | Every 12 hours |
| `86400` | Every day |

Interval scheduling calculates the next run based on the last execution time. If a task has never run, it is immediately considered due.

## Task Options

These options can be configured per task via the dashboard or directly in the state file.

### Prevent Overlap

```yaml
preventOverlap: true  # default
```

When enabled, the scheduler skips execution if the previous run is still marked as `RUNNING`. A staleness check (2× timeout) prevents permanently stuck tasks from blocking future runs.

### Priority

```yaml
priority: 100  # default
```

Lower number = higher priority. When multiple tasks are overdue, they are executed in priority order. Tasks beyond `max_concurrent_tasks` are deferred to the next run.

### Timeout

```yaml
timeout: null  # uses global default_timeout (300s)
```

Maximum execution time in seconds for this specific task. Set to `null` to use the global default.

### Arguments

```yaml
arguments: '--verbose --limit=1000'
```

Additional arguments appended to the command when executed. These are split by whitespace and passed to the process.

## Schedule Overrides

The `defaultExpression` and `defaultInterval` from the attribute are used as initial values when a command is first discovered. After that, the schedule can be changed:

1. **Via dashboard** — Edit task → change cron expression or interval
2. **Via state file** — Manually edit `var/scheduler/state.json`

Changes made via the dashboard or state file take precedence over the attribute defaults.

## Groups

The `group` parameter organizes tasks in the dashboard:

```php
#[AsSchedulableCommand(group: 'maintenance')]  // cleanup, pruning
#[AsSchedulableCommand(group: 'reporting')]    // report generation
#[AsSchedulableCommand(group: 'gdpr')]         // data privacy tasks
#[AsSchedulableCommand(group: 'queue')]        // queue processing
```

Tasks are displayed grouped by their group label in the dashboard. The CLI `list` command also supports filtering by group:

```bash
php bin/console caeligo:scheduler:list --group=maintenance
```
