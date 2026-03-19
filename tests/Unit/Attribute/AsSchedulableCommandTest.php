<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Tests\Unit\Attribute;

use Caeligo\SchedulerBundle\Attribute\AsSchedulableCommand;
use PHPUnit\Framework\TestCase;

class AsSchedulableCommandTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attribute = new AsSchedulableCommand();

        $this->assertEquals('', $attribute->description);
        $this->assertNull($attribute->defaultExpression);
        $this->assertNull($attribute->defaultInterval);
        $this->assertEquals('default', $attribute->group);
    }

    public function testCustomValues(): void
    {
        $attribute = new AsSchedulableCommand(
            description: 'Test description',
            defaultExpression: '0 * * * *',
            group: 'maintenance',
        );

        $this->assertEquals('Test description', $attribute->description);
        $this->assertEquals('0 * * * *', $attribute->defaultExpression);
        $this->assertNull($attribute->defaultInterval);
        $this->assertEquals('maintenance', $attribute->group);
    }

    public function testIntervalMode(): void
    {
        $attribute = new AsSchedulableCommand(
            description: 'Interval task',
            defaultInterval: 3600,
        );

        $this->assertEquals(3600, $attribute->defaultInterval);
        $this->assertNull($attribute->defaultExpression);
    }

    public function testIsPhpAttribute(): void
    {
        $reflection = new \ReflectionClass(AsSchedulableCommand::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_CLASS, $attr->flags);
    }
}
