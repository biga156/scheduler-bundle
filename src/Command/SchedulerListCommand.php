<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:list',
    description: 'List all discovered schedulable commands',
)]
class SchedulerListCommand extends Command
{
    public function __construct(
        private readonly TaskDispatcher $taskDispatcher,
        private readonly CronExpressionParser $cronParser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Filter by group')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tasks = $this->taskDispatcher->getAllTasks();
        $groupFilter = $input->getOption('group');

        if (empty($tasks)) {
            $io->warning('No schedulable commands found. Decorate your commands with #[AsSchedulableCommand].');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tasks as $task) {
            if ($groupFilter !== null && $task['group'] !== $groupFilter) {
                continue;
            }

            $schedule = $task['expression']
                ? $this->cronParser->describe($task['expression'])
                : ($task['intervalSeconds'] !== null ? $this->cronParser->describeInterval($task['intervalSeconds']) : 'Not set');

            $enabled = $task['enabled'] ? '<info>✓</info>' : '<fg=red>✗</>';

            $lastRun = 'never';
            if ($task['lastRunAt'] !== null) {
                $dt = new \DateTimeImmutable($task['lastRunAt']);
                $status = $task['lastRunStatus'] ?? 'unknown';
                $lastRun = $dt->format('H:i') . " ({$status})";
            }

            $rows[] = [
                $task['commandName'],
                $schedule,
                $task['group'],
                $enabled,
                $lastRun,
            ];
        }

        if (empty($rows)) {
            $io->info(\sprintf('No tasks found for group "%s".', $groupFilter));

            return Command::SUCCESS;
        }

        $io->table(
            ['Command', 'Schedule', 'Group', 'Enabled', 'Last Run'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
