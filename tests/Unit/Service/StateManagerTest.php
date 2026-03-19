<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Service;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use Caeligo\SchedulerBundle\Service\StateManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class StateManagerTest extends TestCase
{
    private string $tempDir;
    private StateManager $stateManager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caeligo_scheduler_test_' . uniqid();
        $this->stateManager = new StateManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testGetStateDirReturnsConfiguredPath(): void
    {
        $this->assertEquals($this->tempDir, $this->stateManager->getStateDir());
    }

    public function testLoadStateReturnsEmptyArrayWhenNoFile(): void
    {
        $this->assertEquals([], $this->stateManager->loadState());
    }

    public function testSaveAndLoadState(): void
    {
        $state = [
            'app:test:command' => [
                'enabled' => true,
                'expression' => '0 * * * *',
            ],
        ];

        $this->stateManager->saveState($state);
        $loaded = $this->stateManager->loadState();

        $this->assertEquals($state, $loaded);
    }

    public function testGetTaskStateReturnsDefaultWhenNotExist(): void
    {
        $taskState = $this->stateManager->getTaskState('nonexistent');
        $default = $this->stateManager->defaultTaskState();

        $this->assertEquals($default, $taskState);
    }

    public function testDefaultTaskState(): void
    {
        $default = $this->stateManager->defaultTaskState();

        $this->assertFalse($default['enabled']);
        $this->assertNull($default['expression']);
        $this->assertNull($default['intervalSeconds']);
        $this->assertNull($default['lastRunAt']);
        $this->assertTrue($default['preventOverlap']);
        $this->assertEquals(100, $default['priority']);
        $this->assertEquals('', $default['arguments']);
    }

    public function testUpdateTaskStateMergesValues(): void
    {
        $this->stateManager->updateTaskState('app:test', [
            'enabled' => true,
            'expression' => '*/5 * * * *',
        ]);

        $state = $this->stateManager->getTaskState('app:test');
        $this->assertTrue($state['enabled']);
        $this->assertEquals('*/5 * * * *', $state['expression']);
        // Default values should still be present
        $this->assertTrue($state['preventOverlap']);
    }

    public function testUpdateTaskStatePreservesExistingValues(): void
    {
        $this->stateManager->updateTaskState('app:test', [
            'enabled' => true,
            'expression' => '*/5 * * * *',
        ]);

        $this->stateManager->updateTaskState('app:test', [
            'priority' => 50,
        ]);

        $state = $this->stateManager->getTaskState('app:test');
        $this->assertTrue($state['enabled']);
        $this->assertEquals('*/5 * * * *', $state['expression']);
        $this->assertEquals(50, $state['priority']);
    }

    public function testIsEnabled(): void
    {
        $this->assertFalse($this->stateManager->isEnabled('app:test'));

        $this->stateManager->setEnabled('app:test', true);
        $this->assertTrue($this->stateManager->isEnabled('app:test'));

        $this->stateManager->setEnabled('app:test', false);
        $this->assertFalse($this->stateManager->isEnabled('app:test'));
    }

    public function testMarkRunning(): void
    {
        $this->stateManager->markRunning('app:test');

        $state = $this->stateManager->getTaskState('app:test');
        $this->assertEquals(TaskRunStatus::RUNNING->value, $state['lastRunStatus']);
        $this->assertNotNull($state['lastRunAt']);
    }

    public function testMarkCompleted(): void
    {
        $nextRun = new \DateTimeImmutable('+1 hour');
        $this->stateManager->markCompleted('app:test', TaskRunStatus::SUCCESS, 0, 1.234, $nextRun);

        $state = $this->stateManager->getTaskState('app:test');
        $this->assertEquals(TaskRunStatus::SUCCESS->value, $state['lastRunStatus']);
        $this->assertEquals(0, $state['lastRunExitCode']);
        $this->assertEquals(1.234, $state['lastRunDuration']);
        $this->assertEquals($nextRun->format(\DateTimeInterface::ATOM), $state['nextRunAt']);
    }

    public function testMarkSkipped(): void
    {
        $this->stateManager->markSkipped('app:test');

        $state = $this->stateManager->getTaskState('app:test');
        $this->assertEquals(TaskRunStatus::SKIPPED->value, $state['lastRunStatus']);
    }

    public function testAppendAndReadLogs(): void
    {
        $logEntry = [
            'command' => 'app:test',
            'startedAt' => '2026-03-19T14:00:00+00:00',
            'status' => 'success',
            'duration' => 1.234,
            'exitCode' => 0,
            'output' => 'Test output',
            'errorOutput' => '',
        ];

        $this->stateManager->appendLog('app:test', $logEntry);
        $this->stateManager->appendLog('app:test', array_merge($logEntry, ['startedAt' => '2026-03-19T15:00:00+00:00']));

        $logs = $this->stateManager->readLogs('app:test');
        $this->assertCount(2, $logs);
        // Most recent first
        $this->assertEquals('2026-03-19T15:00:00+00:00', $logs[0]['startedAt']);
    }

    public function testReadLogsReturnsEmptyWhenNoFile(): void
    {
        $this->assertEquals([], $this->stateManager->readLogs('nonexistent'));
    }

    public function testReadLogsWithLimit(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->stateManager->appendLog('app:test', [
                'command' => 'app:test',
                'startedAt' => \sprintf('2026-03-19T%02d:00:00+00:00', $i),
                'status' => 'success',
            ]);
        }

        $logs = $this->stateManager->readLogs('app:test', 3);
        $this->assertCount(3, $logs);
    }

    public function testReadAllLogs(): void
    {
        $this->stateManager->appendLog('app:test1', [
            'command' => 'app:test1',
            'startedAt' => '2026-03-19T14:00:00+00:00',
            'status' => 'success',
        ]);
        $this->stateManager->appendLog('app:test2', [
            'command' => 'app:test2',
            'startedAt' => '2026-03-19T15:00:00+00:00',
            'status' => 'failed',
        ]);

        $logs = $this->stateManager->readAllLogs();
        $this->assertCount(2, $logs);
        // Sorted by startedAt desc
        $this->assertEquals('app:test2', $logs[0]['command']);
    }

    public function testCleanupLogsRemovesOldEntries(): void
    {
        $stateManager = new StateManager($this->tempDir, logRetentionDays: 1);

        $stateManager->appendLog('app:test', [
            'command' => 'app:test',
            'startedAt' => (new \DateTimeImmutable('-2 days'))->format(\DateTimeInterface::ATOM),
            'status' => 'success',
        ]);
        $stateManager->appendLog('app:test', [
            'command' => 'app:test',
            'startedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'status' => 'success',
        ]);

        $removed = $stateManager->cleanupLogs();
        $this->assertEquals(1, $removed);

        $logs = $stateManager->readLogs('app:test');
        $this->assertCount(1, $logs);
    }

    public function testCleanupLogsReturnsZeroWhenNoLogs(): void
    {
        $this->assertEquals(0, $this->stateManager->cleanupLogs());
    }

    public function testLogOutputConfigRespected(): void
    {
        $stateManager = new StateManager($this->tempDir, logOutput: false, logErrorOutput: false);

        $stateManager->appendLog('app:test', [
            'command' => 'app:test',
            'startedAt' => '2026-03-19T14:00:00+00:00',
            'status' => 'success',
            'output' => 'should be removed',
            'errorOutput' => 'should also be removed',
        ]);

        $logs = $stateManager->readLogs('app:test');
        $this->assertCount(1, $logs);
        $this->assertArrayNotHasKey('output', $logs[0]);
        $this->assertArrayNotHasKey('errorOutput', $logs[0]);
    }

    public function testSanitizeCommandNameForLogFile(): void
    {
        // Commands with colons should be safely stored
        $this->stateManager->appendLog('app:gdpr:anonymize-data', [
            'command' => 'app:gdpr:anonymize-data',
            'startedAt' => '2026-03-19T14:00:00+00:00',
            'status' => 'success',
        ]);

        $logs = $this->stateManager->readLogs('app:gdpr:anonymize-data');
        $this->assertCount(1, $logs);
    }
}
