<?php

declare(strict_types=1);

namespace App;

use Framework\One;

final class Application extends One
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    protected function setupRequest(): void
    {
        $this->getRequestContext()['startTime'] = (int) floor(microtime(true) * 1000);
    }
}