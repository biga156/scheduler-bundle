# HTTP Trigger

An alternative scheduling mechanism for shared hosting environments without crontab access.

## Overview

Some shared hosting environments do not provide crontab access. The HTTP trigger allows an external web cron service (e.g., [cron-job.org](https://cron-job.org), [EasyCron](https://www.easycron.com/)) to trigger the scheduler via an HTTP request.

> **This feature is disabled by default** and must be explicitly enabled.

## Configuration

```yaml
# config/packages/caeligo_scheduler.yaml
caeligo_scheduler:
    http_trigger:
        enabled: true
        secret: 'your-strong-secret-here'  # Required!
```

> **Important:** The `secret` must be set when enabling the HTTP trigger. Use a long, random string.

## How It Works

1. You configure the bundle with `http_trigger.enabled: true` and a `secret`
2. The bundle generates a URL with an HMAC-SHA256 token
3. You configure an external web cron service to ping this URL every minute
4. The endpoint calls `TaskDispatcher::dispatchOverdue()` — same as the CLI command

## Security

- The trigger URL includes an HMAC-SHA256 token computed from the project directory and the configured `secret`
- Requests without a valid token are rejected with a 403 response
- The secret should be kept confidential and rotated periodically

## External Web Cron Services

| Service | Free Tier | Minimum Interval |
|---------|-----------|------------------|
| [cron-job.org](https://cron-job.org) | Yes | 1 minute |
| [EasyCron](https://www.easycron.com/) | Limited | 1 minute |
| [UptimeRobot](https://uptimerobot.com/) | Yes (5 min) | 5 minutes |

Configure the service to send a GET request to your trigger URL every minute (or your preferred interval).

## When to Use

Use the HTTP trigger **only** when:
- You have no crontab access (shared hosting)
- You cannot run background processes
- The `caeligo:scheduler:install` command reports `UNSUPPORTED`

For all other environments, prefer the crontab-based approach — it's more reliable and doesn't depend on external services.

## Limitations

- Depends on an external service (less reliable than crontab)
- Minimum interval depends on the external service
- HTTP request overhead adds latency
- The web server must be running for the trigger to work
