<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsSchedulableCommand
{
    public function __construct(
        public readonly string $description = '',
        public readonly ?string $defaultExpression = null,
        public readonly ?int $defaultInterval = null,
        public readonly string $group = 'default',
    ) {
    }
}
