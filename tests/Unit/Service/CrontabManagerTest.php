<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Service;

use Caeligo\SchedulerBundle\Service\CrontabManager;
use PHPUnit\Framework\TestCase;

class CrontabManagerTest extends TestCase
{
    public function testBuildCrontabLine(): void
    {
        $manager = new CrontabManager('/usr/bin/php', '/var/www/project');
        $line = $manager->buildCrontabLine();

        $this->assertStringContainsString('* * * * *', $line);
        $this->assertStringContainsString('caeligo:scheduler:run', $line);
        $this->assertStringContainsString('/var/www/project', $line);
        $this->assertStringContainsString('# caeligo-scheduler', $line);
    }

    public function testBuildCrontabLineUsesEscapedPaths(): void
    {
        $manager = new CrontabManager('/usr/bin/php8.2', '/var/www/my project');
        $line = $manager->buildCrontabLine();

        // Should have escaped paths (single quotes from escapeshellarg)
        $this->assertStringContainsString("'/var/www/my project'", $line);
        $this->assertStringContainsString("'/usr/bin/php8.2'", $line);
    }
}
