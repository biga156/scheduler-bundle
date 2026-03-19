<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Service;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use Caeligo\SchedulerBundle\Service\CommandDiscoveryService;
use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Caeligo\SchedulerBundle\Service\StateManager;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TaskDispatcherTest extends TestCase
{
    private string $tempDir;
    private StateManager $stateManager;
    private CommandDiscoveryService&MockObject $discoveryService;
    private CronExpressionParser $cronParser;
    private TaskDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caeligo_dispatcher_test_' . uniqid();
        $this->stateManager = new StateManager($this->tempDir);
        $this->discoveryService = $this->createMock(CommandDiscoveryService::class);
        $this->cronParser = new CronExpressionParser();

        $this->dispatcher = new TaskDispatcher(
            stateManager: $this->stateManager,
            discoveryService: $this->discoveryService,
            cronParser: $this->cronParser,
            phpBinary: \PHP_BINARY,
            projectDir: '/tmp',
            defaultTimeout: 300,
            maxConcurrent: 5,
        );
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testGetAllTasksMergesDiscoveredAndState(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:test' => [
                'commandName' => 'app:test',
                'description' => 'Test command',
                'defaultExpression' => '0 * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->stateManager->updateTaskState('app:test', [
            'enabled' => true,
            'expression' => '*/5 * * * *',
        ]);

        $tasks = $this->dispatcher->getAllTasks();

        $this->assertArrayHasKey('app:test', $tasks);
        $this->assertEquals('Test command', $tasks['app:test']['description']);
        $this->assertTrue($tasks['app:test']['enabled']);
        // State expression overrides default
        $this->assertEquals('*/5 * * * *', $tasks['app:test']['expression']);
    }

    public function testGetAllTasksUsesDefaultExpressionWhenNoState(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:test' => [
                'commandName' => 'app:test',
                'description' => 'Test',
                'defaultExpression' => '0 * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $tasks = $this->dispatcher->getAllTasks();

        $this->assertEquals('0 * * * *', $tasks['app:test']['expression']);
        $this->assertFalse($tasks['app:test']['enabled']);
    }

    public function testGetOverdueTasksFiltersDisabled(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:test' => [
                'commandName' => 'app:test',
                'description' => 'Test',
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        // Not enabled, so shouldn't be overdue
        $overdue = $this->dispatcher->getOverdueTasks();
        $this->assertEmpty($overdue);
    }

    public function testGetOverdueTasksIncludesEnabledDueTasks(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:test' => [
                'commandName' => 'app:test',
                'description' => 'Test',
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->stateManager->updateTaskState('app:test', [
            'enabled' => true,
            'expression' => '* * * * *',
        ]);

        $overdue = $this->dispatcher->getOverdueTasks();
        $this->assertArrayHasKey('app:test', $overdue);
    }

    public function testGetOverdueTasksSkipsRunningWithOverlapPrevention(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:test' => [
                'commandName' => 'app:test',
                'description' => 'Test',
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->stateManager->updateTaskState('app:test', [
            'enabled' => true,
            'expression' => '* * * * *',
            'preventOverlap' => true,
            'lastRunStatus' => TaskRunStatus::RUNNING->value,
            'lastRunAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $overdue = $this->dispatcher->getOverdueTasks();
        $this->assertEmpty($overdue);
    }

    public function testSyncDiscoveredCommandsCreatesNewEntries(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:new-command' => [
                'commandName' => 'app:new-command',
                'description' => 'New command',
                'defaultExpression' => '0 3 * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->dispatcher->syncDiscoveredCommands();

        $state = $this->stateManager->getTaskState('app:new-command');
        $this->assertEquals('0 3 * * *', $state['expression']);
    }

    public function testSyncDiscoveredCommandsDoesNotOverwriteExisting(): void
    {
        $this->stateManager->updateTaskState('app:existing', [
            'enabled' => true,
            'expression' => '*/10 * * * *',
        ]);

        $this->discoveryService->method('discover')->willReturn([
            'app:existing' => [
                'commandName' => 'app:existing',
                'description' => 'Existing',
                'defaultExpression' => '0 * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->dispatcher->syncDiscoveredCommands();

        $state = $this->stateManager->getTaskState('app:existing');
        $this->assertTrue($state['enabled']);
        $this->assertEquals('*/10 * * * *', $state['expression']);
    }

    public function testExecuteTaskThrowsForNonSchedulable(): void
    {
        $this->discoveryService->method('isSchedulable')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->dispatcher->executeTask('nonexistent');
    }

    public function testGetOverdueTasksRespectsMaxConcurrent(): void
    {
        $commands = [];
        $stateUpdates = [];

        for ($i = 0; $i < 10; ++$i) {
            $name = "app:test{$i}";
            $commands[$name] = [
                'commandName' => $name,
                'description' => "Test {$i}",
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ];
        }

        $this->discoveryService->method('discover')->willReturn($commands);

        foreach ($commands as $name => $info) {
            $this->stateManager->updateTaskState($name, [
                'enabled' => true,
                'expression' => '* * * * *',
            ]);
        }

        $overdue = $this->dispatcher->getOverdueTasks();
        $this->assertLessThanOrEqual(5, \count($overdue));
    }

    public function testGetOverdueTasksSortedByPriority(): void
    {
        $this->discoveryService->method('discover')->willReturn([
            'app:low' => [
                'commandName' => 'app:low',
                'description' => 'Low priority',
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
            'app:high' => [
                'commandName' => 'app:high',
                'description' => 'High priority',
                'defaultExpression' => '* * * * *',
                'defaultInterval' => null,
                'group' => 'default',
            ],
        ]);

        $this->stateManager->updateTaskState('app:low', [
            'enabled' => true,
            'expression' => '* * * * *',
            'priority' => 200,
        ]);
        $this->stateManager->updateTaskState('app:high', [
            'enabled' => true,
            'expression' => '* * * * *',
            'priority' => 10,
        ]);

        $overdue = $this->dispatcher->getOverdueTasks();
        $keys = array_keys($overdue);
        $this->assertEquals('app:high', $keys[0]);
    }
}
