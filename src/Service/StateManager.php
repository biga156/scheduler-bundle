<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Service;

use Caeligo\SchedulerBundle\Enum\TaskRunStatus;
use Symfony\Component\Filesystem\Filesystem;

class StateManager
{
    private readonly Filesystem $filesystem;
    private readonly string $stateFile;
    private readonly string $lockFile;
    private readonly string $logsDir;

    public function __construct(
        private readonly string $stateDir,
        private readonly int $logRetentionDays = 30,
        private readonly bool $logOutput = true,
        private readonly bool $logErrorOutput = true,
    ) {
        $this->filesystem = new Filesystem();
        $this->stateFile = $this->stateDir . '/state.json';
        $this->lockFile = $this->stateDir . '/state.json.lock';
        $this->logsDir = $this->stateDir . '/logs';
    }

    public function getStateDir(): string
    {
        return $this->stateDir;
    }

    public function loadState(): array
    {
        $this->ensureDirectoryExists();

        if (!file_exists($this->stateFile)) {
            return [];
        }

        $content = file_get_contents($this->stateFile);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($data) ? $data : [];
    }

    public function saveState(array $state): void
    {
        $this->ensureDirectoryExists();

        $this->withFileLock(function () use ($state): void {
            $json = json_encode($state, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
            $this->filesystem->dumpFile($this->stateFile, $json);
        });
    }

    public function getTaskState(string $commandName): array
    {
        $state = $this->loadState();

        return $state[$commandName] ?? $this->defaultTaskState();
    }

    public function updateTaskState(string $commandName, array $updates): void
    {
        $this->withFileLock(function () use ($commandName, $updates): void {
            $state = $this->loadState();
            $current = $state[$commandName] ?? $this->defaultTaskState();
            $state[$commandName] = array_merge($current, $updates);

            $json = json_encode($state, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
            $this->filesystem->dumpFile($this->stateFile, $json);
        });
    }

    public function defaultTaskState(): array
    {
        return [
            'enabled' => false,
            'expression' => null,
            'intervalSeconds' => null,
            'lastRunAt' => null,
            'lastRunDuration' => null,
            'lastRunStatus' => null,
            'lastRunExitCode' => null,
            'nextRunAt' => null,
            'preventOverlap' => true,
            'priority' => 100,
            'timeout' => null,
            'arguments' => '',
        ];
    }

    public function isEnabled(string $commandName): bool
    {
        $taskState = $this->getTaskState($commandName);

        return $taskState['enabled'] ?? false;
    }

    public function setEnabled(string $commandName, bool $enabled): void
    {
        $this->updateTaskState($commandName, ['enabled' => $enabled]);
    }

    public function markRunning(string $commandName): void
    {
        $this->updateTaskState($commandName, [
            'lastRunStatus' => TaskRunStatus::RUNNING->value,
            'lastRunAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function markCompleted(
        string $commandName,
        TaskRunStatus $status,
        int $exitCode,
        float $duration,
        ?\DateTimeImmutable $nextRunAt = null,
    ): void {
        $updates = [
            'lastRunStatus' => $status->value,
            'lastRunExitCode' => $exitCode,
            'lastRunDuration' => round($duration, 3),
        ];

        if ($nextRunAt !== null) {
            $updates['nextRunAt'] = $nextRunAt->format(\DateTimeInterface::ATOM);
        }

        $this->updateTaskState($commandName, $updates);
    }

    public function markSkipped(string $commandName): void
    {
        $this->updateTaskState($commandName, [
            'lastRunStatus' => TaskRunStatus::SKIPPED->value,
        ]);
    }

    public function appendLog(string $commandName, array $logEntry): void
    {
        $this->ensureDirectoryExists();
        $this->filesystem->mkdir($this->logsDir, 0755);

        $safeCommandName = $this->sanitizeCommandName($commandName);
        $logFile = $this->logsDir . '/' . $safeCommandName . '.jsonl';

        $filteredEntry = $logEntry;
        if (!$this->logOutput) {
            unset($filteredEntry['output']);
        }
        if (!$this->logErrorOutput) {
            unset($filteredEntry['errorOutput']);
        }

        $line = json_encode($filteredEntry, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($logFile, $line, \FILE_APPEND | \LOCK_EX);
    }

    public function readLogs(string $commandName, int $limit = 50): array
    {
        $safeCommandName = $this->sanitizeCommandName($commandName);
        $logFile = $this->logsDir . '/' . $safeCommandName . '.jsonl';

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $lines = \array_slice($lines, -$limit);
        $logs = [];

        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if (\is_array($entry)) {
                $logs[] = $entry;
            }
        }

        return $logs;
    }

    public function readAllLogs(int $limit = 100): array
    {
        if (!is_dir($this->logsDir)) {
            return [];
        }

        $allLogs = [];
        $files = glob($this->logsDir . '/*.jsonl');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $lines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (\is_array($entry)) {
                    $allLogs[] = $entry;
                }
            }
        }

        usort($allLogs, static function (array $a, array $b): int {
            return ($b['startedAt'] ?? '') <=> ($a['startedAt'] ?? '');
        });

        return \array_slice($allLogs, 0, $limit);
    }

    public function clearLogs(string $commandName): int
    {
        $safeCommandName = $this->sanitizeCommandName($commandName);
        $logFile = $this->logsDir . '/' . $safeCommandName . '.jsonl';

        if (!file_exists($logFile)) {
            return 0;
        }

        $lines = file($logFile, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        $count = $lines !== false ? \count($lines) : 0;

        $this->filesystem->remove($logFile);

        return $count;
    }

    public function clearAllLogs(): int
    {
        if (!is_dir($this->logsDir)) {
            return 0;
        }

        $files = glob($this->logsDir . '/*.jsonl');
        if ($files === false || $files === []) {
            return 0;
        }

        $totalRemoved = 0;
        foreach ($files as $file) {
            $lines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
            $totalRemoved += $lines !== false ? \count($lines) : 0;
        }

        foreach ($files as $file) {
            $this->filesystem->remove($file);
        }

        return $totalRemoved;
    }

    public function cleanupLogs(): int
    {
        if (!is_dir($this->logsDir)) {
            return 0;
        }

        $cutoff = new \DateTimeImmutable("-{$this->logRetentionDays} days");
        $cutoffStr = $cutoff->format(\DateTimeInterface::ATOM);
        $totalRemoved = 0;

        $files = glob($this->logsDir . '/*.jsonl');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $lines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            $kept = [];
            $removed = 0;

            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (\is_array($entry) && isset($entry['startedAt']) && $entry['startedAt'] >= $cutoffStr) {
                    $kept[] = $line;
                } else {
                    ++$removed;
                }
            }

            if ($removed > 0) {
                file_put_contents($file, implode("\n", $kept) . ($kept ? "\n" : ''), \LOCK_EX);
                $totalRemoved += $removed;
            }
        }

        return $totalRemoved;
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->stateDir)) {
            $this->filesystem->mkdir($this->stateDir, 0755);
        }
    }

    private function sanitizeCommandName(string $commandName): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $commandName) ?? $commandName;
    }

    private function withFileLock(callable $callback): void
    {
        $this->ensureDirectoryExists();

        $lockHandle = fopen($this->lockFile, 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException(\sprintf('Cannot open lock file: %s', $this->lockFile));
        }

        try {
            if (!flock($lockHandle, \LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire file lock');
            }

            try {
                $callback();
            } finally {
                flock($lockHandle, \LOCK_UN);
            }
        } finally {
            fclose($lockHandle);
        }
    }
}
