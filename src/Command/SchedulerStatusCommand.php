<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\CrontabManager;
use Caeligo\SchedulerBundle\Service\StateManager;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:status',
    description: 'Show overall scheduler health and status',
)]
class SchedulerStatusCommand extends Command
{
    public function __construct(
        private readonly TaskDispatcher $taskDispatcher,
        private readonly CrontabManager $crontabManager,
        private readonly StateManager $stateManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Caeligo Scheduler Status');

        // Crontab
        $crontabStatus = $this->crontabManager->getStatus();
        $statusLabel = match ($crontabStatus) {
            'INSTALLED' => '<info>INSTALLED</info>',
            'NOT_INSTALLED' => '<fg=red>NOT INSTALLED</>',
            default => '<fg=gray>UNSUPPORTED</>',
        };
        $io->writeln(\sprintf('  Crontab: %s', $statusLabel));

        if ($crontabStatus === 'INSTALLED') {
            $entry = $this->crontabManager->getInstalledEntry();
            $io->writeln(\sprintf('  Entry:   %s', $entry));
        }

        // State
        $io->writeln(\sprintf('  State dir: %s', $this->stateManager->getStateDir()));

        // Tasks
        $tasks = $this->taskDispatcher->getAllTasks();
        $total = \count($tasks);
        $enabled = \count(array_filter($tasks, static fn (array $t): bool => $t['enabled']));
        $failed = \count(array_filter($tasks, static fn (array $t): bool => $t['lastRunStatus'] === 'failed'));

        $io->newLine();
        $io->writeln(\sprintf('  Tasks:   %d total, %d enabled, %d failed', $total, $enabled, $failed));

        // Per-task current status (matches UI)
        $io->newLine();
        $io->section('Task Status');

        $taskRows = [];
        foreach ($tasks as $task) {
            $status = match ($task['lastRunStatus']) {
                'success' => '<info>success</info>',
                'failed' => '<fg=red>failed</>',
                'running' => '<fg=yellow>running</>',
                'skipped' => '<fg=gray>skipped</>',
                default => $task['lastRunStatus'] ?? '<fg=gray>never</>',
            };

            $lastRun = '-';
            if ($task['lastRunAt'] !== null) {
                $dt = new \DateTimeImmutable($task['lastRunAt']);
                $lastRun = $dt->format('Y-m-d H:i');
            }

            $nextRun = '-';
            if ($task['nextRunAt'] !== null) {
                $dt = new \DateTimeImmutable($task['nextRunAt']);
                $nextRun = $dt->format('Y-m-d H:i');
            }

            $taskRows[] = [
                $task['commandName'],
                $task['enabled'] ? '<info>✓</info>' : '<fg=red>✗</>',
                $status,
                $lastRun,
                $nextRun,
            ];
        }
        $io->table(['Command', 'On', 'Last Status', 'Last Run', 'Next Run'], $taskRows);

        // Recent log history
        $io->section('Recent Log History');

        $recentLogs = $this->stateManager->readAllLogs(5);
        if (empty($recentLogs)) {
            $io->writeln('  No execution history yet.');
        } else {
            $rows = [];
            foreach ($recentLogs as $log) {
                $logStatus = match ($log['status'] ?? null) {
                    'success' => '<info>success</info>',
                    'failed' => '<fg=red>failed</>',
                    default => $log['status'] ?? '-',
                };
                $rows[] = [
                    $log['command'] ?? 'unknown',
                    $log['startedAt'] ?? '-',
                    $logStatus,
                    isset($log['duration']) ? \sprintf('%.3fs', $log['duration']) : '-',
                ];
            }
            $io->table(['Command', 'Started', 'Status', 'Duration'], $rows);
        }

        return Command::SUCCESS;
    }
}
