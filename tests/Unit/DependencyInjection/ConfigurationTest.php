<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\DependencyInjection;

use Caeligo\SchedulerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);

        $this->assertEquals('/scheduler', $config['route_prefix']);
        $this->assertEquals('ROLE_ADMIN', $config['role_dashboard']);
        $this->assertEquals('ROLE_SUPER_ADMIN', $config['role_crontab']);
        $this->assertEquals(300, $config['default_timeout']);
        $this->assertEquals(5, $config['max_concurrent_tasks']);
        $this->assertNull($config['php_binary']);
        $this->assertEquals(30, $config['log_retention_days']);
        $this->assertTrue($config['log_output']);
        $this->assertTrue($config['log_error_output']);
        $this->assertNull($config['state_dir']);
        $this->assertFalse($config['http_trigger']['enabled']);
        $this->assertNull($config['http_trigger']['secret']);
    }

    public function testCustomConfiguration(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [[
            'route_prefix' => '/admin/scheduler',
            'role_dashboard' => 'ROLE_SCHEDULER',
            'role_crontab' => 'ROLE_ADMIN',
            'default_timeout' => 600,
            'max_concurrent_tasks' => 10,
            'php_binary' => '/usr/bin/php8.2',
            'log_retention_days' => 60,
            'log_output' => false,
            'state_dir' => '/tmp/scheduler',
            'http_trigger' => [
                'enabled' => true,
                'secret' => 'my-secret',
            ],
        ]]);

        $this->assertEquals('/admin/scheduler', $config['route_prefix']);
        $this->assertEquals('ROLE_SCHEDULER', $config['role_dashboard']);
        $this->assertEquals(600, $config['default_timeout']);
        $this->assertEquals(10, $config['max_concurrent_tasks']);
        $this->assertEquals('/usr/bin/php8.2', $config['php_binary']);
        $this->assertEquals(60, $config['log_retention_days']);
        $this->assertFalse($config['log_output']);
        $this->assertEquals('/tmp/scheduler', $config['state_dir']);
        $this->assertTrue($config['http_trigger']['enabled']);
        $this->assertEquals('my-secret', $config['http_trigger']['secret']);
    }
}
