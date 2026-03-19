<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Command;

use Caeligo\SchedulerBundle\Service\CrontabManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'caeligo:scheduler:uninstall',
    description: 'Remove the scheduler crontab entry',
)]
class SchedulerUninstallCommand extends Command
{
    public function __construct(
        private readonly CrontabManager $crontabManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Removing scheduler crontab entry...');

        $result = $this->crontabManager->uninstall();

        if ($result['success']) {
            $io->success($result['message']);

            return Command::SUCCESS;
        }

        $io->error($result['message']);

        return Command::FAILURE;
    }
}
