<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CaeligoSchedulerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
