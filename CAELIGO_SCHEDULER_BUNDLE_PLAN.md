# Caeligo Scheduler Bundle — Full Implementation Plan

Date: 2026-03-19
Status: approved for implementation
Package: `caeligo/scheduler-bundle`
License: MIT
Repository: TBD (separate repo, published to Packagist)

---

## 1) Decisions Confirmed

- **File-based storage** — no database tables, no Doctrine migrations. State stored in JSON files under `var/scheduler/`.
- **No API controller** — no remote REST management. Dashboard uses standard Symfony form POST + redirect.
- **Full CLI** — 9 console commands for complete terminal-based management alongside the web UI.
- **Attribute-based discovery** — only commands decorated with `#[AsSchedulableCommand]` are visible.
- **Standalone-first** — the bundle ships its own Twig dashboard. EasyAdmin integration is optional.
- **Cron expression + simple interval** — both scheduling modes supported.
- **No sudo for crontab** — the web server user manages its own crontab via `crontab -l` / `crontab -`.
- **Auto-increment int IDs not needed** — no database entities at all.
- **DB-only locking not needed** — file-lock based (flock) for concurrent write protection.

---

## 2) Bundle Structure

```
caeligo/scheduler-bundle/
├── src/
│   ├── Attribute/
│   │   └── AsSchedulableCommand.php
│   ├── Command/
│   │   ├── SchedulerRunCommand.php
│   │   ├── SchedulerListCommand.php
│   │   ├── SchedulerStatusCommand.php
│   │   ├── SchedulerRunNowCommand.php
│   │   ├── SchedulerEnableCommand.php
│   │   ├── SchedulerDisableCommand.php
│   │   ├── SchedulerInstallCommand.php
│   │   ├── SchedulerUninstallCommand.php
│   │   └── SchedulerLogsCommand.php
│   ├── Controller/
│   │   └── SchedulerDashboardController.php
│   ├── DependencyInjection/
│   │   ├── CaeligoSchedulerExtension.php
│   │   └── Configuration.php
│   ├── Enum/
│   │   └── TaskRunStatus.php
│   ├── Service/
│   │   ├── CommandDiscoveryService.php
│   │   ├── CronExpressionParser.php
│   │   ├── CrontabManager.php
│   │   ├── StateManager.php
│   │   └── TaskDispatcher.php
│   ├── Twig/
│   │   └── SchedulerExtension.php
│   └── CaeligoSchedulerBundle.php
├── config/
│   └── routes.yaml
├── templates/
│   ├── dashboard/
│   │   ├── index.html.twig
│   │   ├── task_edit.html.twig
│   │   ├── task_logs.html.twig
│   │   └── settings.html.twig
│   ├── _partials/
│   │   ├── _task_table.html.twig
│   │   ├── _log_table.html.twig
│   │   ├── _status_badge.html.twig
│   │   └── _flash_messages.html.twig
│   └── base.html.twig
├── composer.json
├── LICENSE
├── README.md
└── tests/
```

---

## 3) Dependencies

### composer.json `require`

| Package | Version | Purpose |
|---------|---------|---------|
| `php` | `>=8.2` | PHP 8.2+ for readonly properties, enums, attributes |
| `symfony/framework-bundle` | `^6.4 \|\| ^7.0` | Symfony framework integration |
| `symfony/console` | `^6.4 \|\| ^7.0` | Console commands |
| `symfony/process` | `^6.4 \|\| ^7.0` | Subprocess execution for tasks |
| `symfony/dependency-injection` | `^6.4 \|\| ^7.0` | DI container |
| `symfony/config` | `^6.4 \|\| ^7.0` | Configuration tree |
| `symfony/filesystem` | `^6.4 \|\| ^7.0` | File operations (state files) |
| `symfony/twig-bundle` | `^6.4 \|\| ^7.0` | Twig templates |
| `symfony/routing` | `^6.4 \|\| ^7.0` | Dashboard routes |
| `symfony/security-bundle` | `^6.4 \|\| ^7.0` | Role-based access |
| `dragonmantank/cron-expression` | `^3.3` | Cron expression parsing |

### `suggest`

| Package | Purpose |
|---------|---------|
| `easycorp/easyadmin-bundle ^4.0` | For EasyAdmin dashboard integration |
| `symfony/lock ^6.4` | For distributed mutex in multi-server environments |

### `require-dev`

| Package | Purpose |
|---------|---------|
| `phpunit/phpunit ^10.0 \|\| ^11.0` | Unit and integration tests |
| `symfony/phpunit-bridge ^6.4 \|\| ^7.0` | Symfony test tools |

---

## 4) Component Details

### 4.1 — AsSchedulableCommand attribute

File: `src/Attribute/AsSchedulableCommand.php`

PHP attribute placed on command classes alongside `#[AsCommand]`:

```php
#[Attribute(Attribute::TARGET_CLASS)]
class AsSchedulableCommand
{
    public function __construct(
        public readonly string $description = '',
        public readonly ?string $defaultExpression = null,
        public readonly ?int $defaultInterval = null,
        public readonly string $group = 'default',
    ) {}
}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `description` | string | Human-readable description for the scheduler UI |
| `defaultExpression` | ?string | Suggested cron expression (e.g. `0 * * * *`) |
| `defaultInterval` | ?int | Alternative: simple interval in seconds (e.g. 3600) |
| `group` | string | Grouping label for UI (e.g. `maintenance`, `gdpr`) |

Usage example:
```php
#[AsCommand(name: 'app:promotion:maintenance')]
#[AsSchedulableCommand(
    description: 'Mark expired promotions as ENDED',
    defaultExpression: '0 * * * *',
    group: 'maintenance',
)]
class PromotionMaintenanceCommand extends Command { ... }
```

### 4.2 — TaskRunStatus enum

File: `src/Enum/TaskRunStatus.php`

```php
enum TaskRunStatus: string {
    case SUCCESS = 'success';
    case FAILED  = 'failed';
    case RUNNING = 'running';
    case SKIPPED = 'skipped';
}
```

Methods: `label(): string`, `badgeClass(): string` (returns CSS class: success/danger/warning/secondary).

### 4.3 — StateManager service

File: `src/Service/StateManager.php`

File-based state and log storage. All data in `var/scheduler/`:
- `state.json` — per-task runtime state
- `logs/*.jsonl` — per-task execution log (JSON Lines format)

**State file structure** (`state.json`):
```json
{
    "app:promotion:maintenance": {
        "enabled": true,
        "expression": "0 * * * *",
        "intervalSeconds": null,
        "lastRunAt": "2026-03-19T14:00:00+00:00",
        "lastRunDuration": 0.234,
        "lastRunStatus": "success",
        "lastRunExitCode": 0,
        "nextRunAt": "2026-03-19T15:00:00+00:00",
        "preventOverlap": true,
        "priority": 100,
        "timeout": null,
        "arguments": ""
    }
}
```

**Methods:**

| Method | Purpose |
|--------|---------|
| `loadState(): array` | Read full state.json |
| `saveState(array): void` | Write full state.json with file lock |
| `getTaskState(string): array` | Get state for one task |
| `updateTaskState(string, array): void` | Merge-update one task's state |
| `defaultTaskState(): array` | Default values for a new task |
| `isEnabled(string): bool` | Check if task is enabled |
| `setEnabled(string, bool): void` | Toggle task |
| `markRunning(string): void` | Set status to RUNNING |
| `markCompleted(string, status, exitCode, duration, nextRunAt): void` | Record completion |
| `markSkipped(string): void` | Record skipped (overlap) |
| `appendLog(string, array): void` | Append to JSONL log file |
| `readLogs(string, limit): array` | Read last N logs for a task |
| `readAllLogs(limit): array` | Read recent logs across all tasks |
| `cleanupLogs(): int` | Remove entries older than retention |

**Concurrency**: File lock (`flock`) on `state.json.lock` for write operations.

### 4.4 — CommandDiscoveryService

File: `src/Service/CommandDiscoveryService.php`

Discovers commands decorated with `#[AsSchedulableCommand]` via the Symfony Application.

**Algorithm:**
1. Create `Application` from kernel
2. Iterate all registered commands
3. Use reflection to check for `AsSchedulableCommand` attribute
4. Return array keyed by command name with metadata

**Methods:**

| Method | Purpose |
|--------|---------|
| `discover(): array` | Return all schedulable commands |
| `isSchedulable(string): bool` | Check if command name is schedulable |
| `getCommandInfo(string): ?array` | Get metadata for one command |
| `getGroups(): array` | Unique group names |

Results cached per request (property cache, no persistent cache).

### 4.5 — CronExpressionParser

File: `src/Service/CronExpressionParser.php`

Wraps `dragonmantank/cron-expression` and handles both cron expressions and simple intervals.

**Methods:**

| Method | Purpose |
|--------|---------|
| `getNextRunDate(expression, from): DateTimeImmutable` | Next run from cron expression |
| `getNextRunDateFromInterval(seconds, lastRun): DateTimeImmutable` | Next run from interval |
| `isDue(expression, at): bool` | Is cron expression due now? |
| `isIntervalDue(seconds, lastRun, now): bool` | Is interval due? |
| `isValidExpression(expression): bool` | Validate cron expression |
| `describe(expression): string` | Human-readable description |
| `describeInterval(seconds): string` | Human-readable interval |
| `calculateNextRun(expression, interval, lastRun): ?DateTimeImmutable` | Unified next-run calculation |
| `isTaskDue(expression, interval, lastRun, now): bool` | Unified due-check |

### 4.6 — TaskDispatcher

File: `src/Service/TaskDispatcher.php`

Core execution engine.

**Constructor dependencies:**
- `StateManager`, `CommandDiscoveryService`, `CronExpressionParser`
- `$phpBinary`, `$projectDir`, `$defaultTimeout`, `$maxConcurrent`

**Methods:**

| Method | Purpose |
|--------|---------|
| `getAllTasks(): array` | Merge attribute metadata + file state for all tasks |
| `getOverdueTasks(): array` | Filter tasks that are due, respecting overlap/priority/concurrency |
| `executeTask(command, arguments): array` | Run one task as subprocess, update state + logs |
| `dispatchOverdue(): array` | Run all overdue tasks (main scheduler loop entry) |
| `syncDiscoveredCommands(): void` | Ensure all attribute-discovered commands have state entries |

**Execution flow:**
1. Mark task as RUNNING in state
2. Build `Process` command: `[php, bin/console, commandName, --no-interaction, ...args]`
3. Set timeout, working directory
4. `$process->run()` — synchronous subprocess
5. Capture output, exit code, duration
6. Calculate next run
7. Update state (markCompleted)
8. Append JSONL log entry

**Overlap prevention:**
- If `preventOverlap` is true and status is RUNNING:
  - Check staleness: if running for > 2× timeout → consider dead, allow restart
  - Otherwise → markSkipped, skip execution

### 4.7 — CrontabManager

File: `src/Service/CrontabManager.php`

Manages the web server user's crontab (no sudo required).

**Key insight:** Every Linux user can manage their own crontab. PHP runs as `www-data` (or similar), so `crontab -l` and `crontab -` operate on that user's schedule.

**Constructor dependencies:**
- `$phpBinary`, `$projectDir`

**Methods:**

| Method | Purpose |
|--------|---------|
| `getStatus(): string` | `INSTALLED` / `NOT_INSTALLED` / `UNSUPPORTED` |
| `install(): array` | Install crontab entry, returns `[success: bool, message: string]` |
| `uninstall(): array` | Remove crontab entry |
| `getInstalledEntry(): ?string` | Return the scheduler line from crontab |
| `buildCrontabLine(): string` | Build the `* * * * *` command string |

**Install algorithm:**
1. Run `crontab -l` via Process to read current crontab
2. Check if scheduler entry already present (idempotent)
3. Append: `* * * * * cd {projectDir} && {phpBinary} bin/console caeligo:scheduler:run >> /dev/null 2>&1`
4. Pipe combined entries to `crontab -` to write
5. Verify by reading back

**Security:**
- Crontab entry is strictly template-based — no user input in the command string
- Only `ROLE_SUPER_ADMIN` (configurable) can install/uninstall from the web UI
- CSRF protection on all POST actions

### 4.8 — Twig Extension

File: `src/Twig/SchedulerExtension.php`

Twig filters and functions for the dashboard templates.

**Filters:**
- `scheduler_describe` — cron expression or interval → human-readable string
- `scheduler_badge` — TaskRunStatus → HTML badge element
- `scheduler_duration` — float seconds → formatted duration (e.g. "1.2s", "2m 15s")
- `scheduler_ago` — ISO datetime string → relative time ("3 minutes ago")

### 4.9 — Console Commands

9 commands for complete CLI management:

| Command | Name | Description |
|---------|------|-------------|
| `SchedulerRunCommand` | `caeligo:scheduler:run` | **The crontab dispatcher** — check for overdue tasks, execute them. Options: `--dry-run` |
| `SchedulerListCommand` | `caeligo:scheduler:list` | Table of all discovered `#[AsSchedulableCommand]` with schedule, status, next run |
| `SchedulerStatusCommand` | `caeligo:scheduler:status` | Overall health: crontab status, last runs, errors, state file info |
| `SchedulerRunNowCommand` | `caeligo:scheduler:run-now` | Execute one specific task immediately: `caeligo:scheduler:run-now app:promotion:maintenance` |
| `SchedulerEnableCommand` | `caeligo:scheduler:enable` | Enable a task: `caeligo:scheduler:enable app:promotion:maintenance` |
| `SchedulerDisableCommand` | `caeligo:scheduler:disable` | Disable a task: `caeligo:scheduler:disable app:promotion:maintenance` |
| `SchedulerInstallCommand` | `caeligo:scheduler:install` | Install crontab entry for the web server user |
| `SchedulerUninstallCommand` | `caeligo:scheduler:uninstall` | Remove crontab entry |
| `SchedulerLogsCommand` | `caeligo:scheduler:logs` | Show recent execution logs. Options: `--command=NAME`, `--limit=N`, `--status=success\|failed` |

**SchedulerRunCommand details:**
- Called by crontab every minute: `* * * * * cd /path && php bin/console caeligo:scheduler:run`
- Calls `TaskDispatcher::syncDiscoveredCommands()` then `TaskDispatcher::dispatchOverdue()`
- `--dry-run` shows what would run without executing
- Runs log cleanup (`StateManager::cleanupLogs()`)
- Exit code: 0 = all ok, 1 = some tasks failed

**SchedulerListCommand output:**
```
╔══════════════════════════════════╤══════════════╤═══════════════╤══════════╤════════════════════╗
║ Command                          │ Schedule     │ Group         │ Enabled  │ Last Run           ║
╠══════════════════════════════════╪══════════════╪═══════════════╪══════════╪════════════════════╣
║ app:promotion:maintenance        │ 0 * * * *    │ maintenance   │ ✓        │ 14:00 (success)   ║
║ app:cart:abandonment-maintenance │ 0 * * * *    │ maintenance   │ ✓        │ 14:00 (success)   ║
║ app:gdpr:anonymize-guest-data   │ 0 3 * * *    │ gdpr          │ ✓        │ 03:00 (success)   ║
║ app:gdpr:process-inactive-users │ 0 3 * * *    │ gdpr          │ ✗        │ never              ║
╚══════════════════════════════════╧══════════════╧═══════════════╧══════════╧════════════════════╝
```

### 4.10 — SchedulerDashboardController

File: `src/Controller/SchedulerDashboardController.php`

Standalone Symfony controller (NOT EasyAdmin) with standard form POST + redirect pattern.

**Routes** (prefixed with configurable `route_prefix`, default `/scheduler`):

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/` | `index()` | Task list dashboard |
| GET | `/task/{command}/edit` | `editTask()` | Edit form for one task |
| POST | `/task/{command}/edit` | `editTaskSubmit()` | Save task configuration |
| POST | `/task/{command}/toggle` | `toggleTask()` | Enable/disable toggle |
| POST | `/task/{command}/run` | `runNow()` | Execute task immediately |
| GET | `/task/{command}/logs` | `taskLogs()` | Execution history for one task |
| GET | `/logs` | `allLogs()` | All execution logs |
| GET | `/settings` | `settings()` | Crontab status, settings |
| POST | `/crontab/install` | `installCrontab()` | Install crontab entry |
| POST | `/crontab/uninstall` | `uninstallCrontab()` | Remove crontab entry |

All POST routes: CSRF protected.
All routes: role-gated (`role_dashboard` config, default `ROLE_ADMIN`). Crontab routes additionally gated by `role_crontab` (default `ROLE_SUPER_ADMIN`).

### 4.11 — Templates

Standalone HTML templates — no EasyAdmin dependency. Self-contained CSS (inline or minimal bundled).

**`base.html.twig`** — minimal layout with:
- Responsive sidebar/nav: Tasks, Logs, Settings
- Bootstrap 5 CSS (CDN or inline subset)
- Flash message display
- CSRF token helper

**`dashboard/index.html.twig`** — main page:
- Task table grouped by `group` field
- Columns: Command, Description, Schedule (human-readable), Group, Enabled, Last Run (status badge + time), Next Run, Actions
- Actions: Edit, Toggle (form POST), Run Now (form POST), Logs
- Color-coded status badges (green/red/yellow/gray)

**`dashboard/task_edit.html.twig`** — edit form:
- Schedule type selector: Cron Expression / Simple Interval
- Cron expression input + validation feedback
- Interval: numeric input + unit selector (minutes/hours/days)
- Arguments input (optional)
- Prevent overlap toggle
- Priority input
- Timeout override (optional)
- Save / Cancel buttons

**`dashboard/task_logs.html.twig`** — log viewer:
- Paginated log table
- Columns: Started At, Duration, Status, Exit Code, Output (expandable)
- Status filter dropdown

**`dashboard/settings.html.twig`** — settings page:
- Crontab status: INSTALLED (green) / NOT INSTALLED (red) / UNSUPPORTED (gray)
- Install / Uninstall button (CSRF protected form POST)
- Crontab entry preview (read-only)
- State file location
- Log retention days (display only — configured in bundle config)

---

## 5) Configuration

### Bundle config tree (`caeligo_scheduler.yaml`):

```yaml
caeligo_scheduler:
    route_prefix: '/scheduler'              # URL prefix for standalone dashboard
    role_dashboard: 'ROLE_ADMIN'            # Role to view/manage tasks
    role_crontab: 'ROLE_SUPER_ADMIN'        # Role to install/uninstall crontab
    default_timeout: 300                     # Task execution timeout (seconds)
    max_concurrent_tasks: 5                  # Max parallel task executions
    php_binary: null                         # Auto-detect from PHP_BINARY if null
    log_retention_days: 30                   # Auto-cleanup old log entries
    log_output: true                         # Store stdout in logs
    log_error_output: true                   # Store stderr in logs
    state_dir: null                          # Defaults to %kernel.project_dir%/var/scheduler
    http_trigger:
        enabled: false                       # HTTP trigger for shared hosting
        secret: null                         # HMAC secret for trigger URL
```

### Route configuration (`config/routes.yaml` in bundle):

```yaml
caeligo_scheduler:
    resource: '@CaeligoSchedulerBundle/config/routes.yaml'
    prefix: '%caeligo_scheduler.route_prefix%'
```

---

## 6) DI Extension

File: `src/DependencyInjection/CaeligoSchedulerExtension.php`

Registers:
- Container parameters from config tree
- Services: StateManager, CommandDiscoveryService, CronExpressionParser, TaskDispatcher, CrontabManager
- Console commands (9 commands, tagged `console.command`)
- Controller (tagged `controller.service_arguments`)
- Twig extension (tagged `twig.extension`)

All services use constructor injection, autowired where possible.

---

## 7) EasyAdmin Integration (Optional, Phase 2)

**Not implemented in v1.** Documented approach for future:

1. Bundle detects if `EasyAdminBundle` is registered in kernel
2. If yes: provides additional classes in `src/EasyAdmin/` namespace:
   - `SchedulerMenuItemProvider` — helper to generate menu item for dashboard link
3. Host project adds to `configureMenuItems()`:
   ```php
   yield MenuItem::linkToUrl('Scheduler', 'fa fa-clock', $this->generateUrl('caeligo_scheduler_index'));
   ```
4. No CRUD controllers needed — the standalone dashboard IS the UI

---

## 8) HTTP Trigger Fallback (Optional)

For shared hosting without crontab access:

- Route: `GET /scheduler/trigger/{token}`
- Token: HMAC-SHA256 of project dir + configured secret
- External webcron (e.g., cron-job.org) pings this URL every minute
- Endpoint calls `TaskDispatcher::dispatchOverdue()`
- Disabled by default, explicitly enabled via `http_trigger.enabled: true`

---

## 9) Implementation Phases

### Phase 1: Core Foundation
1. Project scaffolding (composer.json, LICENSE, README)
2. CaeligoSchedulerBundle class + DI Extension + Configuration
3. AsSchedulableCommand attribute
4. TaskRunStatus enum
5. StateManager service (file-based state + log storage)
6. CommandDiscoveryService (attribute reflection)
7. CronExpressionParser (cron-expression wrapper)

### Phase 2: Execution Engine
8. TaskDispatcher service (dispatch, execute, sync)
9. CrontabManager service (crontab read/write)
10. SchedulerRunCommand (the crontab entry point)
11. SchedulerListCommand
12. SchedulerStatusCommand
13. SchedulerRunNowCommand
14. SchedulerEnableCommand + SchedulerDisableCommand
15. SchedulerInstallCommand + SchedulerUninstallCommand
16. SchedulerLogsCommand

### Phase 3: Web Dashboard
17. SchedulerDashboardController (all routes)
18. Twig SchedulerExtension (filters, functions)
19. Templates: base, index, task_edit, task_logs, settings, partials
20. Route configuration

### Phase 4: Polish & Testing
21. Unit tests: CronExpressionParser, StateManager, CrontabManager (mock Process)
22. Integration tests: TaskDispatcher, SchedulerRunCommand
23. Functional tests: dashboard renders, form submissions
24. README documentation
25. PHPStan clean (level 8)

### Phase 5: EasyAdmin Integration (future)
26. Auto-detection of EasyAdmin
27. Menu item helper
28. Integration documentation

---

## 10) Security Considerations

- **CSRF**: All POST form actions use Symfony CSRF tokens
- **Role-gating**: All routes protected by configurable roles
- **Crontab safety**: Entry is strictly template-based — no user input in command string
- **File permissions**: State directory created with 0755, files with 0644
- **No process injection**: Command names validated against discovered schedulable commands only
- **No path traversal**: Command names sanitized when used as filenames
- **HTTP Trigger**: HMAC token prevents unauthorized execution
- **Output truncation**: Large command output truncated in logs to prevent disk filling

---

## 11) Testing Strategy

### Unit Tests
- `CronExpressionParserTest` — expression parsing, interval calculation, describe(), isDue()
- `StateManagerTest` — read/write state, log append/read, cleanup, concurrent access
- `CommandDiscoveryServiceTest` — attribute discovery (mock Application)
- `CrontabManagerTest` — crontab parsing, install/uninstall (mock Process)

### Integration Tests
- `TaskDispatcherTest` — overdue detection, execution flow, overlap prevention
- `SchedulerRunCommandTest` — end-to-end test with real state files

### Functional Tests
- Dashboard pages render (requires Symfony test kernel)
- Form submissions work (toggle, edit, install)
- CLI commands produce expected output

---

## 12) Files to Create (Complete List)

```
src/CaeligoSchedulerBundle.php
src/Attribute/AsSchedulableCommand.php
src/Command/SchedulerRunCommand.php
src/Command/SchedulerListCommand.php
src/Command/SchedulerStatusCommand.php
src/Command/SchedulerRunNowCommand.php
src/Command/SchedulerEnableCommand.php
src/Command/SchedulerDisableCommand.php
src/Command/SchedulerInstallCommand.php
src/Command/SchedulerUninstallCommand.php
src/Command/SchedulerLogsCommand.php
src/Controller/SchedulerDashboardController.php
src/DependencyInjection/CaeligoSchedulerExtension.php
src/DependencyInjection/Configuration.php
src/Enum/TaskRunStatus.php
src/Service/CommandDiscoveryService.php
src/Service/CronExpressionParser.php
src/Service/CrontabManager.php
src/Service/StateManager.php
src/Service/TaskDispatcher.php
src/Twig/SchedulerExtension.php
config/routes.yaml
templates/base.html.twig
templates/dashboard/index.html.twig
templates/dashboard/task_edit.html.twig
templates/dashboard/task_logs.html.twig
templates/dashboard/settings.html.twig
templates/_partials/_task_table.html.twig
templates/_partials/_log_table.html.twig
templates/_partials/_status_badge.html.twig
templates/_partials/_flash_messages.html.twig
composer.json
LICENSE
README.md
tests/
```

Total: ~30 files
