# Architecture & Storage

Technical details about the file-based storage, locking mechanism, and internal architecture.

## Design Principles

- **No database dependency** — All state stored in JSON files
- **No external services** — No Redis, no message queues, no external schedulers
- **File-lock based concurrency** — `flock()` for safe concurrent writes
- **Attribute-driven discovery** — Only `#[AsSchedulableCommand]` commands are visible
- **Subprocess execution** — Tasks run as separate PHP processes via `symfony/process`

## File Structure

All data is stored under the configured `state_dir` (default: `var/scheduler/`):

```
var/scheduler/
├── state.json           # Task runtime state
├── state.json.lock      # File lock for concurrent access
└── logs/
    ├── app_cleanup_expired.jsonl
    ├── app_report_daily.jsonl
    └── ...
```

## State File (`state.json`)

The state file contains per-task runtime configuration and status:

```json
{
    "app:cleanup:expired": {
        "enabled": true,
        "expression": "0 3 * * *",
        "intervalSeconds": null,
        "lastRunAt": "2026-03-19T03:00:00+00:00",
        "lastRunDuration": 1.234,
        "lastRunStatus": "success",
        "lastRunExitCode": 0,
        "nextRunAt": "2026-03-20T03:00:00+00:00",
        "preventOverlap": true,
        "priority": 100,
        "timeout": null,
        "arguments": ""
    }
}
```

### State Fields

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | `bool` | Whether the task is active |
| `expression` | `?string` | Cron expression |
| `intervalSeconds` | `?int` | Interval in seconds |
| `lastRunAt` | `?string` | ISO 8601 timestamp of last execution start |
| `lastRunDuration` | `?float` | Duration in seconds |
| `lastRunStatus` | `?string` | `success`, `failed`, `running`, or `skipped` |
| `lastRunExitCode` | `?int` | Process exit code |
| `nextRunAt` | `?string` | ISO 8601 timestamp of next planned execution |
| `preventOverlap` | `bool` | Skip if previous run still active |
| `priority` | `int` | Execution priority (lower = higher) |
| `timeout` | `?int` | Task-specific timeout override |
| `arguments` | `string` | Additional command arguments |

## Log Files (`logs/*.jsonl`)

Each task has its own log file in [JSON Lines](https://jsonlines.org/) format. Each line is a JSON object:

```json
{"command":"app:cleanup:expired","startedAt":"2026-03-19T03:00:00+00:00","finishedAt":"2026-03-19T03:00:01+00:00","duration":1.234,"status":"success","exitCode":0,"output":"Cleaned 42 records.","errorOutput":""}
```

### Log Entry Fields

| Field | Type | Description |
|-------|------|-------------|
| `command` | `string` | Command name |
| `startedAt` | `string` | ISO 8601 start timestamp |
| `finishedAt` | `string` | ISO 8601 end timestamp |
| `duration` | `float` | Execution duration in seconds |
| `status` | `string` | Task run status |
| `exitCode` | `int` | Process exit code |
| `output` | `string` | Stdout (if `log_output` enabled) |
| `errorOutput` | `string` | Stderr (if `log_error_output` enabled) |

## Concurrency & Locking

### File Lock

Write operations to `state.json` are protected by an exclusive file lock (`flock`) on `state.json.lock`. This prevents corruption when multiple processes (e.g., crontab invocation + manual CLI run) write concurrently.

```
Write flow:
1. Open state.json.lock
2. Acquire LOCK_EX (blocking)
3. Read current state
4. Modify state
5. Write state
6. Release lock
7. Close lock file
```

### Log Writes

Log entries are appended with `FILE_APPEND | LOCK_EX` for atomic line writes.

### Overlap Prevention

When `preventOverlap` is `true`:
1. Before executing, check if `lastRunStatus === 'running'`
2. If running, check staleness: `(now - lastRunAt) > 2 × timeout`
3. If stale → allow execution (previous process is likely dead)
4. If not stale → mark as `skipped`, skip execution

## Execution Flow

The main scheduler flow (`caeligo:scheduler:run`):

```
1. syncDiscoveredCommands()
   └── Ensure all #[AsSchedulableCommand] commands have state entries
   
2. dispatchOverdue()
   ├── getOverdueTasks()
   │   ├── Merge discovered metadata + file state
   │   ├── Filter: enabled, has schedule, is due
   │   ├── Overlap prevention check
   │   ├── Sort by priority
   │   └── Limit to max_concurrent_tasks
   │
   └── For each overdue task:
       ├── markRunning()
       ├── Build Process: [php, bin/console, commandName, --no-interaction, ...args]
       ├── process->run() (synchronous)
       ├── Capture output, exit code, duration
       ├── Calculate next run
       ├── markCompleted()
       └── appendLog()

3. cleanupLogs()
   └── Remove entries older than log_retention_days
```

## Service Architecture

```
TaskDispatcher
├── StateManager         — File-based state and log storage
├── CommandDiscoveryService — Attribute reflection on Application
└── CronExpressionParser — Cron + interval scheduling logic

CrontabManager           — Crontab read/write (independent service)
SchedulerExtension (Twig) — Template filters for the dashboard
SchedulerDashboardController — Web UI using all above services
```

## Output Truncation

Command output is truncated at 64KB to prevent disk filling from verbose commands. Truncated output is marked with `... [truncated]`.

## Command Name Sanitization

When used as log filenames, command names are sanitized: colons and special characters are replaced with underscores. For example, `app:gdpr:anonymize-data` becomes `app_gdpr_anonymize-data.jsonl`.
