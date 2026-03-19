<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\CommandDiscoveryService;
use Caeligo\SchedulerBundle\Service\StateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:disable',
    description: 'Disable a scheduled task',
)]
class SchedulerDisableCommand extends Command
{
    public function __construct(
        private readonly StateManager $stateManager,
        private readonly CommandDiscoveryService $discoveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('command-name', InputArgument::REQUIRED, 'The command name to disable')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandName = $input->getArgument('command-name');

        if (!$this->discoveryService->isSchedulable($commandName)) {
            $io->error(\sprintf('Command "%s" is not a schedulable command.', $commandName));

            return Command::FAILURE;
        }

        $this->stateManager->setEnabled($commandName, false);
        $io->success(\sprintf('Task "%s" has been disabled.', $commandName));

        return Command::SUCCESS;
    }
}
