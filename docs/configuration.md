# Configuration Reference

Full list of all `caeligo_scheduler` configuration options.

## Default Configuration

```yaml
# config/packages/caeligo_scheduler.yaml
caeligo_scheduler:

    # URL prefix for the standalone dashboard
    route_prefix: '/scheduler'

    # Role required to view/manage tasks in the dashboard
    role_dashboard: 'ROLE_ADMIN'

    # Role required to install/uninstall crontab (elevated access)
    role_crontab: 'ROLE_SUPER_ADMIN'

    # Default task execution timeout in seconds (0 = no timeout)
    default_timeout: 300

    # Maximum number of tasks to run per scheduler invocation
    max_concurrent_tasks: 5

    # Path to PHP binary. Auto-detected from PHP_BINARY if null
    php_binary: null

    # Auto-cleanup log entries older than this many days
    log_retention_days: 30

    # Store stdout in execution logs
    log_output: true

    # Store stderr in execution logs
    log_error_output: true

    # Directory for state and log files. Defaults to %kernel.project_dir%/var/scheduler
    state_dir: null

    # HTTP trigger for shared hosting without crontab access
    http_trigger:
        # Enable the HTTP trigger endpoint
        enabled: false
        # HMAC secret for trigger URL authentication
        secret: null
```

## Option Details

### `route_prefix`

- **Type:** `string`
- **Default:** `'/scheduler'`

URL prefix for all dashboard routes. Change this if you want the dashboard under a different path, e.g., `'/admin/scheduler'`.

### `role_dashboard`

- **Type:** `string`
- **Default:** `'ROLE_ADMIN'`

The Symfony security role required to access the dashboard and manage tasks (view, toggle, edit, run). All dashboard routes check this role.

### `role_crontab`

- **Type:** `string`
- **Default:** `'ROLE_SUPER_ADMIN'`

The Symfony security role required to install/uninstall the crontab entry from the web UI. This is a more privileged operation than general task management.

### `default_timeout`

- **Type:** `integer`
- **Default:** `300` (5 minutes)
- **Min:** `0`

Maximum execution time in seconds for each task. Set to `0` for no timeout. Individual tasks can override this in their configuration.

### `max_concurrent_tasks`

- **Type:** `integer`
- **Default:** `5`
- **Min:** `1`

Maximum number of overdue tasks to execute per scheduler invocation. Tasks are prioritized by their `priority` setting (lower = higher priority).

### `php_binary`

- **Type:** `string|null`
- **Default:** `null` (auto-detected)

Path to the PHP binary used for running commands. When `null`, the value of `PHP_BINARY` is used. Set this explicitly if your CLI PHP differs from your web PHP.

### `log_retention_days`

- **Type:** `integer`
- **Default:** `30`
- **Min:** `1`

Execution log entries older than this many days are automatically removed during each scheduler run.

### `log_output`

- **Type:** `boolean`
- **Default:** `true`

Whether to store command stdout in execution logs. Disable this if your commands produce large output and you want to save disk space.

### `log_error_output`

- **Type:** `boolean`
- **Default:** `true`

Whether to store command stderr in execution logs.

### `state_dir`

- **Type:** `string|null`
- **Default:** `null` (resolves to `%kernel.project_dir%/var/scheduler`)

Directory path for the state file (`state.json`) and log files (`logs/*.jsonl`). Make sure this directory is writable by the web server user.

### `http_trigger.enabled`

- **Type:** `boolean`
- **Default:** `false`

Enable the HTTP trigger endpoint for shared hosting environments without crontab access. See [HTTP Trigger](http-trigger.md) for details.

### `http_trigger.secret`

- **Type:** `string|null`
- **Default:** `null`

HMAC secret used to authenticate HTTP trigger requests. **Required** when `http_trigger.enabled` is `true`.

## Environment-Specific Configuration

You can override settings per environment:

```yaml
# config/packages/dev/caeligo_scheduler.yaml
caeligo_scheduler:
    log_retention_days: 7
    default_timeout: 60
```

```yaml
# config/packages/prod/caeligo_scheduler.yaml
caeligo_scheduler:
    log_output: false
    log_error_output: true
```

## Gitignore

Add the state directory to `.gitignore`:

```gitignore
/var/scheduler/
```
