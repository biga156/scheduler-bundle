<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\StateManager;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:run',
    description: 'Run overdue scheduled tasks (crontab entry point)',
)]
class SchedulerRunCommand extends Command
{
    public function __construct(
        private readonly TaskDispatcher $taskDispatcher,
        private readonly StateManager $stateManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would run without executing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $this->taskDispatcher->syncDiscoveredCommands();

        if ($dryRun) {
            $overdue = $this->taskDispatcher->getOverdueTasks();

            if (empty($overdue)) {
                $io->info('No overdue tasks to run.');

                return Command::SUCCESS;
            }

            $io->title('Dry Run — Overdue Tasks');
            foreach ($overdue as $commandName => $task) {
                $io->writeln(\sprintf(
                    '  • <info>%s</info> — %s',
                    $commandName,
                    $task['expression'] ?? (\sprintf('every %ds', $task['intervalSeconds'] ?? 0)),
                ));
            }

            return Command::SUCCESS;
        }

        $results = $this->taskDispatcher->dispatchOverdue();

        // Cleanup old logs
        $cleaned = $this->stateManager->cleanupLogs();
        if ($cleaned > 0 && $output->isVerbose()) {
            $io->note(\sprintf('Cleaned up %d old log entries.', $cleaned));
        }

        if (empty($results)) {
            if ($output->isVerbose()) {
                $io->info('No overdue tasks.');
            }

            return Command::SUCCESS;
        }

        $hasFailure = false;
        foreach ($results as $commandName => $result) {
            if (!$result['success']) {
                $hasFailure = true;
                if ($output->isVerbose()) {
                    $io->error(\sprintf('Task %s failed with exit code %d', $commandName, $result['exitCode']));
                }
            } elseif ($output->isVerbose()) {
                $io->success(\sprintf('Task %s completed in %.3fs', $commandName, $result['duration']));
            }
        }

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }
}
