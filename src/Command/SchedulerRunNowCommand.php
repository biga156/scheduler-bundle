<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:run-now',
    description: 'Execute a specific scheduled task immediately',
)]
class SchedulerRunNowCommand extends Command
{
    public function __construct(
        private readonly TaskDispatcher $taskDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('command-name', InputArgument::REQUIRED, 'The command name to execute')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandName = $input->getArgument('command-name');

        $io->info(\sprintf('Running %s...', $commandName));

        try {
            $result = $this->taskDispatcher->executeTask($commandName);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($result['success']) {
            $io->success(\sprintf(
                'Task %s completed successfully in %.3fs (exit code: %d)',
                $commandName,
                $result['duration'],
                $result['exitCode'],
            ));
        } else {
            $io->error(\sprintf(
                'Task %s failed with exit code %d (duration: %.3fs)',
                $commandName,
                $result['exitCode'],
                $result['duration'],
            ));
        }

        if ($output->isVerbose() && $result['output'] !== '') {
            $io->section('Output');
            $io->writeln($result['output']);
        }

        if ($output->isVerbose() && $result['errorOutput'] !== '') {
            $io->section('Error Output');
            $io->writeln($result['errorOutput']);
        }

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
