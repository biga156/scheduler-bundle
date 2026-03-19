# Testing

How to run the test suite and write tests for the Caeligo Scheduler Bundle.

## Running Tests

### Install Dependencies

```bash
composer install
```

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Integration tests only
vendor/bin/phpunit --testsuite=Integration

# Functional tests only
vendor/bin/phpunit --testsuite=Functional
```

### Run a Specific Test

```bash
vendor/bin/phpunit --filter=CronExpressionParserTest
vendor/bin/phpunit --filter=testIsValidExpression
```

## Test Structure

```
tests/
├── Unit/
│   ├── Attribute/
│   │   └── AsSchedulableCommandTest.php
│   ├── DependencyInjection/
│   │   └── ConfigurationTest.php
│   ├── Enum/
│   │   └── TaskRunStatusTest.php
│   ├── Service/
│   │   ├── CommandDiscoveryServiceTest.php
│   │   ├── CronExpressionParserTest.php
│   │   ├── CrontabManagerTest.php
│   │   ├── StateManagerTest.php
│   │   └── TaskDispatcherTest.php
│   └── Twig/
│       └── SchedulerExtensionTest.php
├── Integration/
└── Functional/
```

## Unit Tests

### CronExpressionParserTest

Tests cron expression parsing, interval calculation, human-readable descriptions, and due-checking:
- Valid/invalid expression detection
- Next run date calculation
- Interval-based scheduling
- `describe()` output for common patterns
- `isTaskDue()` unified checks

### StateManagerTest

Tests file-based state and log storage using a temporary directory:
- State save/load round-trip
- Task state update merging
- Enable/disable toggle
- Mark running/completed/skipped
- Log append and read (with limits)
- Log cleanup by retention
- Output filtering configuration
- Command name sanitization

### CommandDiscoveryServiceTest

Tests attribute-based command discovery using a real Symfony Application with test command fixtures:
- Discovering `#[AsSchedulableCommand]` commands
- Excluding non-decorated commands
- Correct metadata extraction
- Group listing
- Per-request caching

### CrontabManagerTest

Tests crontab line generation (mocked Process for actual crontab operations):
- Correct crontab line format
- Path escaping

### TaskDispatcherTest

Tests the execution engine with mocked `CommandDiscoveryService`:
- Task merging (discovered + state)
- Default expression fallback
- Overdue task filtering
- Overlap prevention
- Priority sorting
- Max concurrent limiting
- Sync discovered commands

### TaskRunStatusTest

Tests the enum values, labels, and badge classes.

### AsSchedulableCommandTest

Tests the attribute constructor, default values, and PHP attribute metadata.

### ConfigurationTest

Tests the Symfony config tree processing with defaults and custom values.

### SchedulerExtensionTest

Tests all Twig filters: describe, badge, formatDuration, timeAgo.

## Writing Your Own Tests

When writing integration tests for the bundle in a host project:

```php
use Caeligo\SchedulerBundle\Service\TaskDispatcher;

class MySchedulerTest extends KernelTestCase
{
    public function testSchedulerDiscoversMyCommands(): void
    {
        self::bootKernel();
        $dispatcher = self::getContainer()->get(TaskDispatcher::class);
        $tasks = $dispatcher->getAllTasks();

        $this->assertArrayHasKey('app:my-command', $tasks);
    }
}
```
