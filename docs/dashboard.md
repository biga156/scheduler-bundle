# Dashboard Guide

The Caeligo Scheduler Bundle ships a standalone web dashboard for managing scheduled tasks.

## Accessing the Dashboard

The dashboard is available at the configured `route_prefix` (default: `/scheduler`). All routes require the `role_dashboard` role (default: `ROLE_ADMIN`).

## Pages

### Tasks Overview (`/scheduler`)

The main dashboard page showing all discovered schedulable commands, grouped by their `group` attribute.

For each task, you can see:
- **Command name** — the Symfony console command
- **Description** — from the `#[AsSchedulableCommand]` attribute
- **Schedule** — human-readable description of the cron expression or interval
- **Enabled** — toggle button (instant, CSRF-protected)
- **Last Run** — status badge with relative time
- **Next Run** — when the task will run next
- **Actions** — Edit, Run Now, Logs

#### Quick Actions

- **Toggle** — Click the ✓/✗ button to enable or disable a task (form POST with CSRF)
- **Run** — Execute the task immediately (confirmation dialog + CSRF)
- **Edit** — Configure the task's schedule and options
- **Logs** — View the execution history

### Task Edit (`/scheduler/task/{command}/edit`)

Edit form for configuring a task's schedule and runtime options:

- **Schedule Type** — Choose between Cron Expression or Simple Interval
- **Cron Expression** — Standard 5-field cron expression (e.g., `0 * * * *`)
- **Interval** — Numeric value with unit selector (minutes, hours, days)
- **Arguments** — Additional arguments passed to the command
- **Prevent Overlap** — Skip if previous run is still in progress
- **Priority** — Lower number = higher priority (affects execution order)
- **Timeout** — Override the global default timeout

### Execution Logs

#### Task Logs (`/scheduler/task/{command}/logs`)

Execution history for a specific task.

#### All Logs (`/scheduler/logs`)

Combined execution history across all tasks, sorted by most recent.

Log entries include:
- Started At
- Duration (formatted)
- Status (color-coded badge)
- Exit Code
- Output (expandable, click "Show" to reveal)

### Settings (`/scheduler/settings`)

System configuration and crontab management:

- **Crontab Status** — Shows whether the scheduler crontab entry is installed
  - Green badge: Installed
  - Red badge: Not Installed
  - Gray badge: Unsupported (no `crontab` command available)
- **Install/Uninstall** — Buttons for managing the crontab entry (requires `role_crontab`)
- **Crontab Entry Preview** — Shows the exact crontab line
- **State Directory** — Location of state and log files

## Route List

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/scheduler` | Task list dashboard |
| GET | `/scheduler/task/{command}/edit` | Edit form |
| POST | `/scheduler/task/{command}/edit` | Save configuration |
| POST | `/scheduler/task/{command}/toggle` | Toggle enable/disable |
| POST | `/scheduler/task/{command}/run` | Run task now |
| GET | `/scheduler/task/{command}/logs` | Task execution logs |
| GET | `/scheduler/logs` | All execution logs |
| GET | `/scheduler/settings` | Settings page |
| POST | `/scheduler/crontab/install` | Install crontab |
| POST | `/scheduler/crontab/uninstall` | Remove crontab |

All POST routes are CSRF-protected.

## Customizing the Route Prefix

```yaml
# config/packages/caeligo_scheduler.yaml
caeligo_scheduler:
    route_prefix: '/admin/scheduler'
```

Then update your route configuration:

```yaml
# config/routes/caeligo_scheduler.yaml
caeligo_scheduler:
    resource: '@CaeligoSchedulerBundle/config/routes.yaml'
    prefix: /admin/scheduler
```

## Responsive Design

The dashboard uses Bootstrap 5 (loaded from CDN) and is fully responsive. On mobile devices, the sidebar collapses and the layout adapts to smaller screens.
