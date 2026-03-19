<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Service;

use Caeligo\SchedulerBundle\Attribute\AsSchedulableCommand;
use Symfony\Component\Console\Command\Command;

class CommandDiscoveryService
{
    private ?array $cache = null;

    /**
     * @param iterable<Command> $commands
     */
    public function __construct(
        private readonly iterable $commands = [],
    ) {
    }

    /**
     * @return array<string, array{commandName: string, description: string, defaultExpression: ?string, defaultInterval: ?int, group: string}>
     */
    public function discover(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        foreach ($this->commands as $command) {
            $info = $this->extractSchedulableInfo($command);
            if ($info !== null) {
                $this->cache[$info['commandName']] = $info;
            }
        }

        return $this->cache;
    }

    public function isSchedulable(string $commandName): bool
    {
        $commands = $this->discover();

        return isset($commands[$commandName]);
    }

    /**
     * @return array{commandName: string, description: string, defaultExpression: ?string, defaultInterval: ?int, group: string}|null
     */
    public function getCommandInfo(string $commandName): ?array
    {
        $commands = $this->discover();

        return $commands[$commandName] ?? null;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->discover() as $info) {
            $groups[$info['group']] = true;
        }

        return array_keys($groups);
    }

    /**
     * @return array{commandName: string, description: string, defaultExpression: ?string, defaultInterval: ?int, group: string}|null
     */
    private function extractSchedulableInfo(Command $command): ?array
    {
        $reflection = new \ReflectionClass($command);
        $attributes = $reflection->getAttributes(AsSchedulableCommand::class);

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();
        $commandName = $command->getName();

        if ($commandName === null) {
            return null;
        }

        return [
            'commandName' => $commandName,
            'description' => $attribute->description ?: ($command->getDescription() ?: ''),
            'defaultExpression' => $attribute->defaultExpression,
            'defaultInterval' => $attribute->defaultInterval,
            'group' => $attribute->group,
        ];
    }
}
