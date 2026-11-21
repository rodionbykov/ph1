<?php

declare(strict_types=1);

namespace Framework;

use Closure;

/**
 * Use with InternalCallAware to route selected internal calls through AOP.
 * Pass the method name, arguments, and a callable for the original method to
 * interceptInternalCall(); without a proxy it invokes the original directly.
 */
trait InterceptsInternalCalls
{
    private ?Closure $internalCallInterceptor = null;

    public function attachInternalCallInterceptor(?callable $interceptor): void
    {
        $this->internalCallInterceptor = $interceptor === null ? null : Closure::fromCallable($interceptor);
    }

    protected function interceptInternalCall(string $method, array $arguments, callable $original): mixed
    {
        if ($this->internalCallInterceptor === null) {
            return $original(...$arguments);
        }

        return ($this->internalCallInterceptor)($method, $arguments, $original);
    }
}
