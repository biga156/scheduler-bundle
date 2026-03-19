<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\StateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:logs',
    description: 'Show recent task execution logs',
)]
class SchedulerLogsCommand extends Command
{
    public function __construct(
        private readonly StateManager $stateManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('command', 'c', InputOption::VALUE_OPTIONAL, 'Filter by command name')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of entries to show', '20')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Filter by status (success|failed|running|skipped)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandFilter = $input->getOption('command');
        $limit = (int) $input->getOption('limit');
        $statusFilter = $input->getOption('status');

        if ($commandFilter !== null) {
            $logs = $this->stateManager->readLogs($commandFilter, $limit * 2);
        } else {
            $logs = $this->stateManager->readAllLogs($limit * 2);
        }

        if ($statusFilter !== null) {
            $logs = array_filter($logs, static fn (array $log): bool => ($log['status'] ?? '') === $statusFilter);
        }

        $logs = \array_slice($logs, 0, $limit);

        if (empty($logs)) {
            $io->info('No log entries found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = [
                $log['command'] ?? 'unknown',
                $log['startedAt'] ?? '-',
                $log['status'] ?? '-',
                $log['exitCode'] ?? '-',
                isset($log['duration']) ? \sprintf('%.3fs', $log['duration']) : '-',
            ];
        }

        $io->table(
            ['Command', 'Started At', 'Status', 'Exit Code', 'Duration'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
