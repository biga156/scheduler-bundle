<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Service;

use Caeligo\SchedulerBundle\Attribute\AsSchedulableCommand;
use Caeligo\SchedulerBundle\Service\CommandDiscoveryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Test command fixtures
#[AsCommand(name: 'test:schedulable')]
#[AsSchedulableCommand(description: 'Test scheduled command', defaultExpression: '*/5 * * * *', group: 'testing')]
class SchedulableTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}

#[AsCommand(name: 'test:not-schedulable')]
class NotSchedulableTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}

#[AsCommand(name: 'test:interval')]
#[AsSchedulableCommand(description: 'Interval command', defaultInterval: 3600, group: 'maintenance')]
class IntervalTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}

class CommandDiscoveryServiceTest extends TestCase
{
    private Application $application;
    private CommandDiscoveryService $service;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->add(new SchedulableTestCommand());
        $this->application->add(new NotSchedulableTestCommand());
        $this->application->add(new IntervalTestCommand());

        $this->service = new CommandDiscoveryService($this->application);
    }

    public function testDiscoverFindsSchedulableCommands(): void
    {
        $discovered = $this->service->discover();

        $this->assertArrayHasKey('test:schedulable', $discovered);
        $this->assertArrayHasKey('test:interval', $discovered);
        $this->assertArrayNotHasKey('test:not-schedulable', $discovered);
    }

    public function testDiscoverExcludesNonSchedulableCommands(): void
    {
        $discovered = $this->service->discover();

        $this->assertArrayNotHasKey('test:not-schedulable', $discovered);
    }

    public function testDiscoverReturnsCorrectMetadata(): void
    {
        $discovered = $this->service->discover();
        $info = $discovered['test:schedulable'];

        $this->assertEquals('test:schedulable', $info['commandName']);
        $this->assertEquals('Test scheduled command', $info['description']);
        $this->assertEquals('*/5 * * * *', $info['defaultExpression']);
        $this->assertNull($info['defaultInterval']);
        $this->assertEquals('testing', $info['group']);
    }

    public function testDiscoverReturnsIntervalMetadata(): void
    {
        $discovered = $this->service->discover();
        $info = $discovered['test:interval'];

        $this->assertNull($info['defaultExpression']);
        $this->assertEquals(3600, $info['defaultInterval']);
        $this->assertEquals('maintenance', $info['group']);
    }

    public function testIsSchedulable(): void
    {
        $this->assertTrue($this->service->isSchedulable('test:schedulable'));
        $this->assertTrue($this->service->isSchedulable('test:interval'));
        $this->assertFalse($this->service->isSchedulable('test:not-schedulable'));
        $this->assertFalse($this->service->isSchedulable('nonexistent'));
    }

    public function testGetCommandInfo(): void
    {
        $info = $this->service->getCommandInfo('test:schedulable');
        $this->assertNotNull($info);
        $this->assertEquals('test:schedulable', $info['commandName']);

        $missing = $this->service->getCommandInfo('nonexistent');
        $this->assertNull($missing);
    }

    public function testGetGroups(): void
    {
        $groups = $this->service->getGroups();

        $this->assertContains('testing', $groups);
        $this->assertContains('maintenance', $groups);
    }

    public function testDiscoveryIsCached(): void
    {
        $first = $this->service->discover();
        $second = $this->service->discover();

        $this->assertSame($first, $second);
    }

    public function testDiscoverWithNullApplication(): void
    {
        $service = new CommandDiscoveryService(null);
        $this->assertEquals([], $service->discover());
    }
}
