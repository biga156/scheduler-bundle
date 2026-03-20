# Caeligo Scheduler Bundle

A file-based task scheduler bundle for Symfony with a built-in web dashboard and full CLI management.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Why Caeligo?

- **No database required** — Fully file-based storage. No Doctrine, no migrations, no extra infrastructure. Install and run in minutes.
- **Built-in web dashboard** — A standalone, ready-to-use Twig UI ships with the bundle. Manage tasks, browse logs, and control your crontab — all from the browser, out of the box.
- **Native PHP attribute discovery** — Use the modern `#[AsSchedulableCommand]` attribute on your commands. No YAML task definitions, no manual registration, no separate entity classes.
- **Crontab management from CLI and web** — Install or uninstall your system crontab entry with a single command or a button click. No manual server configuration needed.
- **Shared hosting friendly** — Works without long-running workers, message queues, or Redis. A simple cron job is all you need. An HTTP trigger fallback is also available.

## Features

- **Cron expressions & simple intervals** — Both scheduling modes supported
- **10 CLI commands** — Full terminal-based management: list, status, enable, disable, run, logs, purge, install, uninstall, and more
- **Overlap prevention** — Skip task execution when a previous run is still in progress
- **Role-based access** — Configurable role requirements for dashboard and crontab management
- **CSRF protection** — All dashboard POST actions are CSRF-protected
- **Automatic log retention** — Old execution logs are cleaned up automatically
- **EasyAdmin integration** — Optional integration for projects already using EasyAdmin
- **Zero-config defaults** — Sensible defaults let you get started with minimal configuration

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.x
- No database or additional services required

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
| `caeligo:scheduler:purge-logs [command]` | Purge execution logs (all or per task) |

> For detailed CLI usage, see [CLI Reference](docs/cli.md).

## Web Dashboard

A standalone web dashboard ships with the bundle — no separate admin package or frontend build step needed. Available at the configured `route_prefix` (default: `/scheduler`).

- **Tasks** — View, enable/disable, edit, and run tasks directly from the browser
- **Logs** — View execution history with full output details and status indicators. Clear logs per task or globally.
- **Settings** — Install or remove the system crontab entry with a single click

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

## Makefile Integration (Optional)

If your project uses a Makefile (common in Symfony projects), you can add convenient shortcut targets:

```makefile
SYMFONY_CONSOLE = php bin/console

cron-list: ## List all schedulable commands
	@$(SYMFONY_CONSOLE) caeligo:scheduler:list

cron-status: ## Scheduler health status
	@$(SYMFONY_CONSOLE) caeligo:scheduler:status

cron-run: ## Run overdue tasks once
	$(SYMFONY_CONSOLE) caeligo:scheduler:run

cron-run-task: ## Run a specific task (usage: make cron-run-task CMD=app:my:command)
ifndef CMD
	@echo "ERROR: CMD is required. Usage: make cron-run-task CMD=app:some:command"
	@exit 1
endif
	$(SYMFONY_CONSOLE) caeligo:scheduler:run-now $(CMD)

cron-logs: ## Show scheduler execution logs
	@$(SYMFONY_CONSOLE) caeligo:scheduler:logs

cron-logs-purge: ## Purge all scheduler logs
	@$(SYMFONY_CONSOLE) caeligo:scheduler:purge-logs

cron-install: ## Install crontab entry
	$(SYMFONY_CONSOLE) caeligo:scheduler:install

cron-uninstall: ## Uninstall crontab entry
	$(SYMFONY_CONSOLE) caeligo:scheduler:uninstall
```

## License

MIT — see [LICENSE](LICENSE).
