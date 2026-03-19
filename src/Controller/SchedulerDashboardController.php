<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Controller;

use Caeligo\SchedulerBundle\Service\CommandDiscoveryService;
use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Caeligo\SchedulerBundle\Service\CrontabManager;
use Caeligo\SchedulerBundle\Service\StateManager;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SchedulerDashboardController extends AbstractController
{
    public function __construct(
        private readonly TaskDispatcher $taskDispatcher,
        private readonly StateManager $stateManager,
        private readonly CommandDiscoveryService $discoveryService,
        private readonly CronExpressionParser $cronParser,
        private readonly CrontabManager $crontabManager,
        private readonly string $roleDashboard = 'ROLE_ADMIN',
        private readonly string $roleCrontab = 'ROLE_SUPER_ADMIN',
        private readonly string $baseTemplate = '@CaeligoScheduler/base.html.twig',
    ) {
    }

    #[Route('/', name: 'caeligo_scheduler_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        $this->taskDispatcher->syncDiscoveredCommands();
        $tasks = $this->taskDispatcher->getAllTasks();

        // Group tasks
        $grouped = [];
        foreach ($tasks as $task) {
            $grouped[$task['group']][] = $task;
        }

        return $this->render('@CaeligoScheduler/dashboard/index.html.twig', [
            'grouped_tasks' => $grouped,
            'tasks' => $tasks,
            'base_template' => $this->baseTemplate,
        ]);
    }

    #[Route('/task/{command}/edit', name: 'caeligo_scheduler_task_edit', methods: ['GET'], requirements: ['command' => '.+'])]
    public function editTask(string $command): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        if (!$this->discoveryService->isSchedulable($command)) {
            throw $this->createNotFoundException(\sprintf('Command "%s" is not schedulable.', $command));
        }

        $taskState = $this->stateManager->getTaskState($command);
        $commandInfo = $this->discoveryService->getCommandInfo($command);

        return $this->render('@CaeligoScheduler/dashboard/task_edit.html.twig', [
            'command' => $command,
            'task_state' => $taskState,
            'command_info' => $commandInfo,
            'base_template' => $this->baseTemplate,
        ]);
    }

    #[Route('/task/{command}/edit', name: 'caeligo_scheduler_task_edit_submit', methods: ['POST'], requirements: ['command' => '.+'])]
    public function editTaskSubmit(Request $request, string $command): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        if (!$this->isCsrfTokenValid('scheduler_edit_' . $command, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('caeligo_scheduler_task_edit', ['command' => $command]);
        }

        if (!$this->discoveryService->isSchedulable($command)) {
            throw $this->createNotFoundException(\sprintf('Command "%s" is not schedulable.', $command));
        }

        $scheduleType = $request->request->getString('schedule_type');
        $expression = null;
        $intervalSeconds = null;

        if ($scheduleType === 'cron') {
            $expression = $request->request->getString('expression');
            if ($expression !== '' && !$this->cronParser->isValidExpression($expression)) {
                $this->addFlash('danger', 'Invalid cron expression.');

                return $this->redirectToRoute('caeligo_scheduler_task_edit', ['command' => $command]);
            }
        } elseif ($scheduleType === 'interval') {
            $intervalValue = $request->request->getInt('interval_value');
            $intervalUnit = $request->request->getString('interval_unit');
            $intervalSeconds = match ($intervalUnit) {
                'minutes' => $intervalValue * 60,
                'hours' => $intervalValue * 3600,
                'days' => $intervalValue * 86400,
                default => $intervalValue,
            };
        }

        $updates = [
            'expression' => $expression,
            'intervalSeconds' => $intervalSeconds,
            'arguments' => $request->request->getString('arguments'),
            'preventOverlap' => $request->request->getBoolean('prevent_overlap'),
            'priority' => $request->request->getInt('priority', 100),
        ];

        $timeoutValue = $request->request->getString('timeout');
        $updates['timeout'] = $timeoutValue !== '' ? (int) $timeoutValue : null;

        // Recalculate next run
        $taskState = $this->stateManager->getTaskState($command);
        $lastRun = isset($taskState['lastRunAt']) ? new \DateTimeImmutable($taskState['lastRunAt']) : null;
        $nextRun = $this->cronParser->calculateNextRun($expression, $intervalSeconds, $lastRun);
        if ($nextRun !== null) {
            $updates['nextRunAt'] = $nextRun->format(\DateTimeInterface::ATOM);
        }

        $this->stateManager->updateTaskState($command, $updates);
        $this->addFlash('success', \sprintf('Task "%s" configuration saved.', $command));

        return $this->redirectToRoute('caeligo_scheduler_index');
    }

    #[Route('/task/{command}/toggle', name: 'caeligo_scheduler_task_toggle', methods: ['POST'], requirements: ['command' => '.+'])]
    public function toggleTask(Request $request, string $command): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        if (!$this->isCsrfTokenValid('scheduler_toggle_' . $command, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('caeligo_scheduler_index');
        }

        $enabled = $this->stateManager->isEnabled($command);
        $this->stateManager->setEnabled($command, !$enabled);

        $this->addFlash('success', \sprintf(
            'Task "%s" has been %s.',
            $command,
            $enabled ? 'disabled' : 'enabled',
        ));

        return $this->redirectToRoute('caeligo_scheduler_index');
    }

    #[Route('/task/{command}/run', name: 'caeligo_scheduler_task_run', methods: ['POST'], requirements: ['command' => '.+'])]
    public function runNow(Request $request, string $command): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        if (!$this->isCsrfTokenValid('scheduler_run_' . $command, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('caeligo_scheduler_index');
        }

        try {
            $result = $this->taskDispatcher->executeTask($command);

            if ($result['success']) {
                $this->addFlash('success', \sprintf('Task "%s" completed successfully (%.3fs).', $command, $result['duration']));
            } else {
                $this->addFlash('danger', \sprintf('Task "%s" failed with exit code %d.', $command, $result['exitCode']));
            }
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('caeligo_scheduler_index');
    }

    #[Route('/task/{command}/logs', name: 'caeligo_scheduler_task_logs', methods: ['GET'], requirements: ['command' => '.+'])]
    public function taskLogs(string $command): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        $logs = $this->stateManager->readLogs($command, 100);
        $commandInfo = $this->discoveryService->getCommandInfo($command);

        return $this->render('@CaeligoScheduler/dashboard/task_logs.html.twig', [
            'command' => $command,
            'logs' => $logs,
            'command_info' => $commandInfo,
            'base_template' => $this->baseTemplate,
        ]);
    }

    #[Route('/logs', name: 'caeligo_scheduler_logs', methods: ['GET'])]
    public function allLogs(): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        $logs = $this->stateManager->readAllLogs(200);

        return $this->render('@CaeligoScheduler/dashboard/task_logs.html.twig', [
            'command' => null,
            'logs' => $logs,
            'command_info' => null,
            'base_template' => $this->baseTemplate,
        ]);
    }

    #[Route('/settings', name: 'caeligo_scheduler_settings', methods: ['GET'])]
    public function settings(): Response
    {
        $this->denyAccessUnlessGranted($this->roleDashboard);

        $crontabStatus = $this->crontabManager->getStatus();
        $crontabEntry = $this->crontabManager->getInstalledEntry();

        return $this->render('@CaeligoScheduler/dashboard/settings.html.twig', [
            'crontab_status' => $crontabStatus,
            'crontab_entry' => $crontabEntry,
            'crontab_preview' => $this->crontabManager->buildCrontabLine(),
            'state_dir' => $this->stateManager->getStateDir(),
            'can_manage_crontab' => $this->isGranted($this->roleCrontab),
            'base_template' => $this->baseTemplate,
        ]);
    }

    #[Route('/crontab/install', name: 'caeligo_scheduler_crontab_install', methods: ['POST'])]
    public function installCrontab(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->roleCrontab);

        if (!$this->isCsrfTokenValid('scheduler_crontab_install', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('caeligo_scheduler_settings');
        }

        $result = $this->crontabManager->install();
        $this->addFlash($result['success'] ? 'success' : 'danger', $result['message']);

        return $this->redirectToRoute('caeligo_scheduler_settings');
    }

    #[Route('/crontab/uninstall', name: 'caeligo_scheduler_crontab_uninstall', methods: ['POST'])]
    public function uninstallCrontab(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->roleCrontab);

        if (!$this->isCsrfTokenValid('scheduler_crontab_uninstall', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('caeligo_scheduler_settings');
        }

        $result = $this->crontabManager->uninstall();
        $this->addFlash($result['success'] ? 'success' : 'danger', $result['message']);

        return $this->redirectToRoute('caeligo_scheduler_settings');
    }
}
