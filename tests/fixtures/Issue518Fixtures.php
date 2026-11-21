<?php

declare(strict_types=1);

namespace TestFixtures\Issue518\Services;

use TestFixtures\Aop\AdvancedReverse;
use TestFixtures\Aop\StackLogService;

final class AdvReverse extends AdvancedReverse
{
    public function setStackLog(StackLogService $stackLog): void
    {
        $stackLog->log('setStackLog');
        $this->stackLogService = $stackLog;
    }
}

namespace TestFixtures\Issue518\Daos;

use TestFixtures\Aop\AdvancedReverse;

final class AdvReverse extends AdvancedReverse
{
    public function doWrap(string $input): string
    {
        return 'wrong-doWrap';
    }
}
