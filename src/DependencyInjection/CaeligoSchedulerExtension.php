<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\DependencyInjection;

use Caeligo\SchedulerBundle\Controller\SchedulerDashboardController;
use Caeligo\SchedulerBundle\Service\CommandDiscoveryService;
use Caeligo\SchedulerBundle\Service\CronExpressionParser;
use Caeligo\SchedulerBundle\Service\CrontabManager;
use Caeligo\SchedulerBundle\Service\StateManager;
use Caeligo\SchedulerBundle\Service\TaskDispatcher;
use Caeligo\SchedulerBundle\Twig\SchedulerExtension;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class CaeligoSchedulerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $phpBinary = $config['php_binary'] ?? \PHP_BINARY;
        $stateDir = $config['state_dir'] ?? $container->getParameter('kernel.project_dir') . '/var/scheduler';

        $container->setParameter('caeligo_scheduler.route_prefix', $config['route_prefix']);
        $container->setParameter('caeligo_scheduler.role_dashboard', $config['role_dashboard']);
        $container->setParameter('caeligo_scheduler.role_crontab', $config['role_crontab']);
        $container->setParameter('caeligo_scheduler.default_timeout', $config['default_timeout']);
        $container->setParameter('caeligo_scheduler.max_concurrent_tasks', $config['max_concurrent_tasks']);
        $container->setParameter('caeligo_scheduler.php_binary', $phpBinary);
        $container->setParameter('caeligo_scheduler.log_retention_days', $config['log_retention_days']);
        $container->setParameter('caeligo_scheduler.log_output', $config['log_output']);
        $container->setParameter('caeligo_scheduler.log_error_output', $config['log_error_output']);
        $container->setParameter('caeligo_scheduler.state_dir', $stateDir);
        $container->setParameter('caeligo_scheduler.http_trigger.enabled', $config['http_trigger']['enabled']);
        $container->setParameter('caeligo_scheduler.http_trigger.secret', $config['http_trigger']['secret']);

        $this->registerServices($container, $config, $phpBinary, $stateDir);
        $this->registerCommands($container);
        $this->registerController($container);
        $this->registerTwigExtension($container);
    }

    private function registerServices(ContainerBuilder $container, array $config, string $phpBinary, string $stateDir): void
    {
        // StateManager
        $stateManager = new Definition(StateManager::class);
        $stateManager->setArgument('$stateDir', $stateDir);
        $stateManager->setArgument('$logRetentionDays', $config['log_retention_days']);
        $stateManager->setArgument('$logOutput', $config['log_output']);
        $stateManager->setArgument('$logErrorOutput', $config['log_error_output']);
        $stateManager->setAutowired(true);
        $container->setDefinition(StateManager::class, $stateManager);

        // CommandDiscoveryService
        $discoveryService = new Definition(CommandDiscoveryService::class);
        $discoveryService->setArgument('$commands', new TaggedIteratorArgument('console.command'));
        $discoveryService->setAutowired(true);
        $container->setDefinition(CommandDiscoveryService::class, $discoveryService);

        // CronExpressionParser
        $cronParser = new Definition(CronExpressionParser::class);
        $container->setDefinition(CronExpressionParser::class, $cronParser);

        // TaskDispatcher
        $taskDispatcher = new Definition(TaskDispatcher::class);
        $taskDispatcher->setArgument('$stateManager', new Reference(StateManager::class));
        $taskDispatcher->setArgument('$discoveryService', new Reference(CommandDiscoveryService::class));
        $taskDispatcher->setArgument('$cronParser', new Reference(CronExpressionParser::class));
        $taskDispatcher->setArgument('$phpBinary', $phpBinary);
        $taskDispatcher->setArgument('$projectDir', '%kernel.project_dir%');
        $taskDispatcher->setArgument('$defaultTimeout', $config['default_timeout']);
        $taskDispatcher->setArgument('$maxConcurrent', $config['max_concurrent_tasks']);
        $container->setDefinition(TaskDispatcher::class, $taskDispatcher);

        // CrontabManager
        $crontabManager = new Definition(CrontabManager::class);
        $crontabManager->setArgument('$phpBinary', $phpBinary);
        $crontabManager->setArgument('$projectDir', '%kernel.project_dir%');
        $container->setDefinition(CrontabManager::class, $crontabManager);
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        $commandClasses = [
            \Caeligo\SchedulerBundle\Command\SchedulerRunCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerListCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerStatusCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerRunNowCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerEnableCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerDisableCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerInstallCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerUninstallCommand::class,
            \Caeligo\SchedulerBundle\Command\SchedulerLogsCommand::class,
        ];

        foreach ($commandClasses as $class) {
            $definition = new Definition($class);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);
            $definition->addTag('console.command');
            $container->setDefinition($class, $definition);
        }
    }

    private function registerController(ContainerBuilder $container): void
    {
        $controller = new Definition(SchedulerDashboardController::class);
        $controller->setAutowired(true);
        $controller->setAutoconfigured(true);
        $controller->addTag('controller.service_arguments');
        $container->setDefinition(SchedulerDashboardController::class, $controller);
    }

    private function registerTwigExtension(ContainerBuilder $container): void
    {
        $twigExtension = new Definition(SchedulerExtension::class);
        $twigExtension->setAutowired(true);
        $twigExtension->addTag('twig.extension');
        $container->setDefinition(SchedulerExtension::class, $twigExtension);
    }

    public function getAlias(): string
    {
        return 'caeligo_scheduler';
    }
}
