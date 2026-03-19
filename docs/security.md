# Security

Security considerations and configurations for the Caeligo Scheduler Bundle.

## Role-Based Access Control

All dashboard routes are protected by configurable Symfony security roles.

### Dashboard Access

```yaml
caeligo_scheduler:
    role_dashboard: 'ROLE_ADMIN'
```

The `role_dashboard` role is required for:
- Viewing the task list
- Editing task configuration
- Enabling/disabling tasks
- Running tasks manually
- Viewing execution logs
- Viewing settings

### Crontab Management

```yaml
caeligo_scheduler:
    role_crontab: 'ROLE_SUPER_ADMIN'
```

The `role_crontab` role (typically more privileged) is required for:
- Installing the crontab entry
- Uninstalling the crontab entry

Users without this role will see the crontab status but cannot modify it.

## CSRF Protection

All POST actions in the dashboard are protected with Symfony CSRF tokens:

- Task toggle (enable/disable)
- Task edit (save configuration)
- Task run (execute now)
- Crontab install
- Crontab uninstall

Each form includes a `_token` hidden field validated server-side. Invalid tokens result in a flash error and redirect.

## Process Execution Safety

### Command Validation

Only commands that are:
1. Registered in the Symfony Application
2. Decorated with `#[AsSchedulableCommand]`

can be executed by the scheduler. Arbitrary command names cannot be injected.

### No Shell Injection

Commands are executed as arrays via `symfony/process` (not shell strings):

```php
$process = new Process([$phpBinary, 'bin/console', $commandName, '--no-interaction']);
```

This prevents shell injection attacks since arguments are passed directly without shell interpretation.

### Crontab Safety

The crontab entry is strictly template-based:
```
* * * * * cd '/path/to/project' && '/usr/bin/php' bin/console caeligo:scheduler:run >> /dev/null 2>&1
```

- Paths are escaped with `escapeshellarg()`
- No user input is incorporated into the crontab command
- The crontab marker (`# caeligo-scheduler`) ensures only the scheduler's own entry is modified

## File System Security

### Permissions

- State directory: created with `0755`
- State and log files: standard system permissions
- Lock file: read/write by the web server user

### Path Traversal Prevention

Command names used as log filenames are sanitized:

```php
preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $commandName)
```

This prevents directory traversal in log file paths.

### Recommended `.gitignore`

```gitignore
/var/scheduler/
```

## HTTP Trigger Security

When the HTTP trigger is enabled for shared hosting:

- Authentication via HMAC-SHA256 token
- Token is derived from the project directory + configured secret
- Disabled by default — must be explicitly enabled
- See [HTTP Trigger](http-trigger.md) for details

## Best Practices

1. **Use strong roles** — Set `role_dashboard` and `role_crontab` to appropriate roles for your application
2. **Restrict crontab access** — Keep `role_crontab` as `ROLE_SUPER_ADMIN` or equivalent
3. **Review commands** — Only decorate trusted commands with `#[AsSchedulableCommand]`
4. **Monitor logs** — Regularly check execution logs for failed tasks
5. **Secure state directory** — Ensure only the web server user can write to `var/scheduler/`
6. **Environment isolation** — Use different configurations for dev/staging/prod
7. **HTTPS** — Always serve the dashboard over HTTPS in production
