<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Service;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use Symfony\Component\Process\Process;

class TaskDispatcher
{
    public function __construct(
        private readonly StateManager $stateManager,
        private readonly CommandDiscoveryService $discoveryService,
        private readonly CronExpressionParser $cronParser,
        private readonly string $phpBinary,
        private readonly string $projectDir,
        private readonly int $defaultTimeout = 300,
        private readonly int $maxConcurrent = 5,
    ) {
    }

    /**
     * @return array<string, array{commandName: string, description: string, group: string, expression: ?string, intervalSeconds: ?int, enabled: bool, lastRunAt: ?string, lastRunStatus: ?string, lastRunDuration: ?float, lastRunExitCode: ?int, nextRunAt: ?string, preventOverlap: bool, priority: int, timeout: ?int, arguments: string}>
     */
    public function getAllTasks(): array
    {
        $discovered = $this->discoveryService->discover();
        $state = $this->stateManager->loadState();
        $tasks = [];

        foreach ($discovered as $commandName => $info) {
            $taskState = $state[$commandName] ?? $this->stateManager->defaultTaskState();

            $tasks[$commandName] = [
                'commandName' => $commandName,
                'description' => $info['description'],
                'group' => $info['group'],
                'expression' => $taskState['expression'] ?? $info['defaultExpression'],
                'intervalSeconds' => $taskState['intervalSeconds'] ?? $info['defaultInterval'],
                'enabled' => $taskState['enabled'] ?? false,
                'lastRunAt' => $taskState['lastRunAt'] ?? null,
                'lastRunStatus' => $taskState['lastRunStatus'] ?? null,
                'lastRunDuration' => $taskState['lastRunDuration'] ?? null,
                'lastRunExitCode' => $taskState['lastRunExitCode'] ?? null,
                'nextRunAt' => $taskState['nextRunAt'] ?? null,
                'preventOverlap' => $taskState['preventOverlap'] ?? true,
                'priority' => $taskState['priority'] ?? 100,
                'timeout' => $taskState['timeout'] ?? null,
                'arguments' => $taskState['arguments'] ?? '',
            ];
        }

        return $tasks;
    }

    /**
     * @return array<string, array>
     */
    public function getOverdueTasks(): array
    {
        $tasks = $this->getAllTasks();
        $overdue = [];
        $now = new \DateTimeImmutable();

        foreach ($tasks as $commandName => $task) {
            if (!$task['enabled']) {
                continue;
            }

            $expression = $task['expression'];
            $intervalSeconds = $task['intervalSeconds'];

            if ($expression === null && $intervalSeconds === null) {
                continue;
            }

            $lastRun = $task['lastRunAt'] !== null
                ? new \DateTimeImmutable($task['lastRunAt'])
                : null;

            if (!$this->cronParser->isTaskDue($expression, $intervalSeconds, $lastRun, $now)) {
                continue;
            }

            // Overlap prevention
            if ($task['preventOverlap'] && $task['lastRunStatus'] === TaskRunStatus::RUNNING->value) {
                $timeout = $task['timeout'] ?? $this->defaultTimeout;
                $staleness = $timeout * 2;

                if ($lastRun !== null && ($now->getTimestamp() - $lastRun->getTimestamp()) < $staleness) {
                    $this->stateManager->markSkipped($commandName);
                    continue;
                }
            }

            $overdue[$commandName] = $task;
        }

        // Sort by priority (lower number = higher priority)
        uasort($overdue, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        // Limit to max concurrent
        return \array_slice($overdue, 0, $this->maxConcurrent, true);
    }

    /**
     * @return array{success: bool, status: TaskRunStatus, exitCode: int, output: string, errorOutput: string, duration: float}
     */
    public function executeTask(string $commandName, string $arguments = ''): array
    {
        if (!$this->discoveryService->isSchedulable($commandName)) {
            throw new \InvalidArgumentException(\sprintf('Command "%s" is not a schedulable command.', $commandName));
        }

        $this->stateManager->markRunning($commandName);

        $cmd = [$this->phpBinary, 'bin/console', $commandName, '--no-interaction'];

        if ($arguments !== '') {
            $extraArgs = preg_split('/\s+/', trim($arguments));
            if ($extraArgs !== false) {
                $cmd = array_merge($cmd, $extraArgs);
            }
        }

        $taskState = $this->stateManager->getTaskState($commandName);
        $timeout = $taskState['timeout'] ?? $this->defaultTimeout;

        $process = new Process($cmd, $this->projectDir);
        $process->setTimeout($timeout > 0 ? (float) $timeout : null);

        $startTime = microtime(true);

        try {
            $process->run();
        } catch (\Throwable) {
            // Process exception (timeout, etc.)
        }

        $duration = microtime(true) - $startTime;
        $exitCode = $process->getExitCode() ?? 1;
        $success = $exitCode === 0;
        $status = $success ? TaskRunStatus::SUCCESS : TaskRunStatus::FAILED;

        $output = $this->truncateOutput($process->getOutput());
        $errorOutput = $this->truncateOutput($process->getErrorOutput());

        // Calculate next run
        $expression = $taskState['expression'] ?? null;
        $intervalSeconds = $taskState['intervalSeconds'] ?? null;
        $nextRunAt = $this->cronParser->calculateNextRun($expression, $intervalSeconds, new \DateTimeImmutable());

        $this->stateManager->markCompleted($commandName, $status, $exitCode, $duration, $nextRunAt);

        // Append log
        $logEntry = [
            'command' => $commandName,
            'startedAt' => (new \DateTimeImmutable())->modify(\sprintf('-%d seconds', (int) ceil($duration)))->format(\DateTimeInterface::ATOM),
            'finishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration' => round($duration, 3),
            'status' => $status->value,
            'exitCode' => $exitCode,
            'output' => $output,
            'errorOutput' => $errorOutput,
        ];

        $this->stateManager->appendLog($commandName, $logEntry);

        return [
            'success' => $success,
            'status' => $status,
            'exitCode' => $exitCode,
            'output' => $output,
            'errorOutput' => $errorOutput,
            'duration' => round($duration, 3),
        ];
    }

    /**
     * @return array<string, array>
     */
    public function dispatchOverdue(): array
    {
        $overdue = $this->getOverdueTasks();
        $results = [];

        foreach ($overdue as $commandName => $task) {
            $results[$commandName] = $this->executeTask($commandName, $task['arguments']);
        }

        return $results;
    }

    public function syncDiscoveredCommands(): void
    {
        $discovered = $this->discoveryService->discover();
        $state = $this->stateManager->loadState();

        foreach ($discovered as $commandName => $info) {
            if (!isset($state[$commandName])) {
                $defaults = $this->stateManager->defaultTaskState();
                $defaults['expression'] = $info['defaultExpression'];
                $defaults['intervalSeconds'] = $info['defaultInterval'];

                $this->stateManager->updateTaskState($commandName, $defaults);
            }
        }
    }

    private function truncateOutput(string $output, int $maxBytes = 65536): string
    {
        if (\strlen($output) <= $maxBytes) {
            return $output;
        }

        return substr($output, 0, $maxBytes) . "\n... [truncated]";
    }
}
