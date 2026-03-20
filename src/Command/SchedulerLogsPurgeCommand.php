<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\StateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:purge-logs',
    description: 'Purge scheduler execution logs (all or for a specific command)',
)]
class SchedulerLogsPurgeCommand extends Command
{
    public function __construct(
        private readonly StateManager $stateManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('command-name', InputArgument::OPTIONAL, 'Clear logs only for this command (omit to clear all)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandName = $input->getArgument('command-name');

        if ($commandName !== null) {
            if (!$io->confirm(\sprintf('Clear all logs for "%s"?', $commandName), false)) {
                $io->note('Aborted.');

                return Command::SUCCESS;
            }

            $removed = $this->stateManager->clearLogs($commandName);
            $io->success(\sprintf('Removed %d log entries for "%s".', $removed, $commandName));
        } else {
            if (!$io->confirm('Clear ALL scheduler logs?', false)) {
                $io->note('Aborted.');

                return Command::SUCCESS;
            }

            $removed = $this->stateManager->clearAllLogs();
            $io->success(\sprintf('Removed %d log entries.', $removed));
        }

        return Command::SUCCESS;
    }
}
