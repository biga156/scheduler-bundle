# Caeligo Scheduler Bundle

A file-based task scheduler bundle for Symfony with a built-in web dashboard and full CLI management.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Features

- **Attribute-based discovery** — Decorate your Symfony commands with `#[AsSchedulableCommand]` to make them schedulable
- **Cron expressions & simple intervals** — Both scheduling modes supported
- **File-based storage** — No database tables or migrations needed; state stored in JSON files
- **Web dashboard** — Standalone Twig-based UI for managing tasks, viewing logs, and configuring crontab
- **Full CLI** — 9 console commands for complete terminal-based management
- **Crontab management** — Install/uninstall the scheduler crontab entry from CLI or web UI
- **Overlap prevention** — Skip task execution when a previous run is still in progress
- **Role-based access** — Configurable role requirements for dashboard and crontab management
- **CSRF protection** — All POST actions are CSRF-protected
- **Log retention** — Automatic cleanup of old execution logs

## Requirements

- PHP 8.2+
- Symfony 6.4+ or 7.0+

## Installation

```bash
composer require caeligo/scheduler-bundle
```

### Enable the Bundle

If you're not using Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Caeligo\SchedulerBundle\CaeligoSchedulerBundle::class => ['all' => true],
];
```

### Configure Routes

Create `config/routes/caeligo_scheduler.yaml`:

```yaml
caeligo_scheduler:
    resource: '@CaeligoSchedulerBundle/config/routes.yaml'
    prefix: /scheduler
```

### Basic Configuration

Create `config/packages/caeligo_scheduler.yaml`:

```yaml
caeligo_scheduler:
    route_prefix: '/scheduler'
    role_dashboard: 'ROLE_ADMIN'
    role_crontab: 'ROLE_SUPER_ADMIN'
    default_timeout: 300
```

> For all configuration options, see [Configuration Reference](docs/configuration.md).

## Quick Start

### 1. Make a Command Schedulable

Add the `#[AsSchedulableCommand]` attribute to any Symfony console command:

```php
use Caeligo\SchedulerBundle\Attribute\AsSchedulableCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:cleanup:expired')]
#[AsSchedulableCommand(
    description: 'Remove expired records from the database',
    defaultExpression: '0 3 * * *',
    group: 'maintenance',
)]
class CleanupExpiredCommand extends Command
{
    // ...
}
```

### 2. Install the Crontab

```bash
php bin/console caeligo:scheduler:install
```

This adds a crontab entry that runs the scheduler every minute:
```
* * * * * cd '/path/to/project' && '/usr/bin/php' bin/console caeligo:scheduler:run >> /dev/null 2>&1 # caeligo-scheduler
```

### 3. Enable Tasks

```bash
php bin/console caeligo:scheduler:enable app:cleanup:expired
```

Or use the web dashboard at `/scheduler`.

### 4. Verify

```bash
php bin/console caeligo:scheduler:list
php bin/console caeligo:scheduler:status
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `caeligo:scheduler:run` | Run overdue scheduled tasks (crontab entry point) |
| `caeligo:scheduler:list` | List all discovered schedulable commands |
| `caeligo:scheduler:status` | Show overall scheduler health and status |
| `caeligo:scheduler:run-now <command>` | Execute a specific task immediately |
| `caeligo:scheduler:enable <command>` | Enable a scheduled task |
| `caeligo:scheduler:disable <command>` | Disable a scheduled task |
| `caeligo:scheduler:install` | Install the scheduler crontab entry |
| `caeligo:scheduler:uninstall` | Remove the scheduler crontab entry |
| `caeligo:scheduler:logs` | Show recent task execution logs |

> For detailed CLI usage, see [CLI Reference](docs/cli.md).

## Web Dashboard

The standalone web dashboard is available at the configured `route_prefix` (default: `/scheduler`).

- **Tasks** — View, enable/disable, edit, and run tasks
- **Logs** — View execution history with output details
- **Settings** — Manage crontab installation

> For dashboard details, see [Dashboard Guide](docs/dashboard.md).

## Documentation

| Document | Description |
|----------|-------------|
| [Configuration Reference](docs/configuration.md) | All configuration options in detail |
| [CLI Reference](docs/cli.md) | Detailed usage of all 9 console commands |
| [Dashboard Guide](docs/dashboard.md) | Web dashboard features and usage |
| [Scheduling Guide](docs/scheduling.md) | Cron expressions, intervals, and task options |
| [Architecture & Storage](docs/architecture.md) | File-based storage, locking, and internals |
| [Security](docs/security.md) | Role-based access, CSRF, and security considerations |
| [HTTP Trigger](docs/http-trigger.md) | Shared hosting fallback via HTTP trigger |
| [EasyAdmin Integration](docs/easyadmin.md) | Optional EasyAdmin dashboard integration |
| [Testing](docs/testing.md) | Running the test suite |

## License

MIT — see [LICENSE](LICENSE).
