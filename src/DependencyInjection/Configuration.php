<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('caeligo_scheduler');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('route_prefix')
                    ->defaultValue('/scheduler')
                    ->info('URL prefix for the standalone dashboard')
                ->end()
                ->scalarNode('role_dashboard')
                    ->defaultValue('ROLE_ADMIN')
                    ->info('Role required to view/manage tasks')
                ->end()
                ->scalarNode('role_crontab')
                    ->defaultValue('ROLE_SUPER_ADMIN')
                    ->info('Role required to install/uninstall crontab')
                ->end()
                ->integerNode('default_timeout')
                    ->defaultValue(300)
                    ->min(0)
                    ->info('Task execution timeout in seconds')
                ->end()
                ->integerNode('max_concurrent_tasks')
                    ->defaultValue(5)
                    ->min(1)
                    ->info('Max parallel task executions')
                ->end()
                ->scalarNode('php_binary')
                    ->defaultNull()
                    ->info('Path to PHP binary. Auto-detected from PHP_BINARY if null')
                ->end()
                ->integerNode('log_retention_days')
                    ->defaultValue(30)
                    ->min(1)
                    ->info('Auto-cleanup log entries older than this many days')
                ->end()
                ->booleanNode('log_output')
                    ->defaultTrue()
                    ->info('Store stdout in logs')
                ->end()
                ->booleanNode('log_error_output')
                    ->defaultTrue()
                    ->info('Store stderr in logs')
                ->end()
                ->scalarNode('state_dir')
                    ->defaultNull()
                    ->info('State directory path. Defaults to %kernel.project_dir%/var/scheduler')
                ->end()
                ->scalarNode('base_template')
                    ->defaultValue('@CaeligoScheduler/base.html.twig')
                    ->info('Base Twig template for the dashboard. Override to integrate with EasyAdmin or other admin panels.')
                ->end()
                ->arrayNode('http_trigger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable HTTP trigger for shared hosting without crontab')
                        ->end()
                        ->scalarNode('secret')
                            ->defaultNull()
                            ->info('HMAC secret for trigger URL authentication')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
