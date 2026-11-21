<?php

declare(strict_types=1);

namespace Framework;

interface InternalCallAware
{
    public function attachInternalCallInterceptor(?callable $interceptor): void;
}
