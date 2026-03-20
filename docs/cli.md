# CLI Reference

All 10 console commands provided by the Caeligo Scheduler Bundle.

## caeligo:scheduler:run

**The crontab entry point.** Checks for overdue tasks and executes them.

```bash
php bin/console caeligo:scheduler:run
php bin/console caeligo:scheduler:run --dry-run
php bin/console caeligo:scheduler:run -v
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Show what would run without executing |
| `-v` | Verbose output: show task results |

This command is called automatically by crontab every minute. It:
1. Syncs discovered commands with state
2. Dispatches all overdue tasks
3. Cleans up old log entries

**Exit codes:**
- `0` — All tasks succeeded (or no tasks to run)
- `1` — One or more tasks failed

---

## caeligo:scheduler:list

Display a table of all discovered schedulable commands.

```bash
php bin/console caeligo:scheduler:list
php bin/console caeligo:scheduler:list --group=maintenance
```

| Option | Description |
|--------|-------------|
| `-g`, `--group` | Filter by group name |

**Example output:**
```
 ------------- -------------------- ------------- --------- -------------------
  Command       Schedule             Group         Enabled   Last Run
 ------------- -------------------- ------------- --------- -------------------
  app:cleanup   Daily at 03:00       maintenance   ✓         03:00 (success)
  app:report    Every 5 minutes      reporting     ✗         never
 ------------- -------------------- ------------- --------- -------------------
```

---

## caeligo:scheduler:status

Show the overall health of the scheduler.

```bash
php bin/console caeligo:scheduler:status
```

Displays:
- Crontab installation status
- Task counts (total, enabled, failed)
- Per-task status table: enabled, schedule description, last run with result, next run

**Example output:**
```
Caeligo Scheduler Status
========================

  Crontab: INSTALLED
  Tasks: 3 total, 3 enabled, 0 failed

 ------------- ---- ---------------- ---------------------- ------------------
  Command       On   Schedule         Last Run                Next Run
 ------------- ---- ---------------- ---------------------- ------------------
  app:cleanup   ✓    Daily at 03:00   2026-03-20 03:00 success   2026-03-21 03:00
  app:report    ✓    Every 5 minutes  2026-03-20 09:15 success   2026-03-20 09:20
  app:billing   ✓    Monthly on 1st   - -                       2026-04-01 00:00
 ------------- ---- ---------------- ---------------------- ------------------
```

---

## caeligo:scheduler:run-now

Execute a specific task immediately, regardless of its schedule.

```bash
php bin/console caeligo:scheduler:run-now app:cleanup:expired
php bin/console caeligo:scheduler:run-now app:cleanup:expired -v
```

| Argument | Description |
|----------|-------------|
| `command-name` | The Symfony command name to execute |

| Option | Description |
|--------|-------------|
| `-v` | Show command output |

The task runs as a subprocess and its result is recorded in the state file and logs.

---

## caeligo:scheduler:enable

Enable a scheduled task.

```bash
php bin/console caeligo:scheduler:enable app:cleanup:expired
```

| Argument | Description |
|----------|-------------|
| `command-name` | The command name to enable |

Only commands decorated with `#[AsSchedulableCommand]` can be enabled.

---

## caeligo:scheduler:disable

Disable a scheduled task.

```bash
php bin/console caeligo:scheduler:disable app:cleanup:expired
```

| Argument | Description |
|----------|-------------|
| `command-name` | The command name to disable |

Disabled tasks are skipped by the scheduler even when they are due.

---

## caeligo:scheduler:install

Install the scheduler crontab entry for the current user.

```bash
php bin/console caeligo:scheduler:install
```

This adds the following entry to the user's crontab:
```
* * * * * cd '/path/to/project' && '/usr/bin/php' bin/console caeligo:scheduler:run >> /dev/null 2>&1 # caeligo-scheduler
```

The operation is idempotent — running it again when already installed has no effect.

> **Note:** No sudo required. The command operates on the current user's crontab via `crontab -l` / `crontab -`.

---

## caeligo:scheduler:uninstall

Remove the scheduler crontab entry.

```bash
php bin/console caeligo:scheduler:uninstall
```

Removes only the scheduler-related line from the crontab, leaving other entries untouched.

---

## caeligo:scheduler:logs

Show recent task execution logs.

```bash
php bin/console caeligo:scheduler:logs
php bin/console caeligo:scheduler:logs --command=app:cleanup:expired
php bin/console caeligo:scheduler:logs --limit=50
php bin/console caeligo:scheduler:logs --status=failed
```

| Option | Short | Description |
|--------|-------|-------------|
| `--command` | `-c` | Filter by command name |
| `--limit` | `-l` | Number of entries to show (default: 20) |
| `--status` | `-s` | Filter by status: `success`, `failed`, `running`, `skipped` |

**Example output:**
```
 -------------------- ------------------------------ --------- ----------- ----------
  Command              Started At                     Status    Exit Code   Duration
 -------------------- ------------------------------ --------- ----------- ----------
  app:cleanup:expired  2026-03-19T14:00:00+00:00      success   0           1.234s
  app:report:daily     2026-03-19T14:00:00+00:00      failed    1           0.567s
 -------------------- ------------------------------ --------- ----------- ----------
```

---

## caeligo:scheduler:purge-logs

Purge execution logs — all logs or only for a specific command.

```bash
php bin/console caeligo:scheduler:purge-logs
php bin/console caeligo:scheduler:purge-logs app:cleanup:expired
```

| Argument | Description |
|----------|-------------|
| `command-name` | *(optional)* Clear logs only for this command. Omit to clear all. |

Requires interactive confirmation (answers "no" by default with `--no-interaction`).
