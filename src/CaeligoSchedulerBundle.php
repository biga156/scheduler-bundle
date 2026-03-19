<?php

declare(strict_types=1);

namespace Caeligo\SchedulerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CaeligoSchedulerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
