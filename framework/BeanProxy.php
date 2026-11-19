<?php

declare(strict_types=1);

namespace Framework;

use Throwable;

final class BeanProxy
{
    public function __construct(
        private object $target,
        private array $interceptors,
    ) {
    }

    public function __call(string $name, array $arguments): mixed
    {
        $call = function (array $args): mixed {
            return $this->target->{$this->currentMethod}(...$args);
        };

        $this->currentMethod = $name;
        $args = $arguments;

        foreach ($this->interceptors as $interceptor) {
            if (!$this->matchesMethod($interceptor['methods'], $name)) {
                continue;
            }

            if (method_exists($interceptor['bean'], 'before')) {
                $interceptor['bean']->before($this->target, $name, $args);
            }
        }

        $around = array_values(array_filter(
            $this->interceptors,
            fn (array $interceptor): bool => $this->matchesMethod($interceptor['methods'], $name)
                && method_exists($interceptor['bean'], 'around')
        ));

        $runner = array_reduce(
            array_reverse($around),
            function (callable $next, array $interceptor): callable {
                return function (array $args) use ($interceptor, $next): mixed {
                    return $interceptor['bean']->around($this->target, $this->currentMethod, $args, $next);
                };
            },
            $call
        );

        try {
            $result = $runner($args);
        } catch (Throwable $exception) {
            foreach ($this->interceptors as $interceptor) {
                if ($this->matchesMethod($interceptor['methods'], $name) && method_exists($interceptor['bean'], 'onError')) {
                    $interceptor['bean']->onError($this->target, $name, $args, $exception);
                }
            }

            throw $exception;
        }

        foreach ($this->interceptors as $interceptor) {
            if (!$this->matchesMethod($interceptor['methods'], $name) || !method_exists($interceptor['bean'], 'after')) {
                continue;
            }

            $afterResult = $interceptor['bean']->after($this->target, $name, $args, $result);
            if ($afterResult !== null) {
                $result = $afterResult;
            }
        }

        return $result;
    }

    public function __get(string $name): mixed
    {
        return $this->target->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->target->{$name} = $value;
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    private string $currentMethod = '';

    private function matchesMethod(string $methods, string $name): bool
    {
        $methods = trim($methods);
        if ($methods === '' || $methods === '*') {
            return true;
        }

        $allowed = array_map('trim', explode(',', $methods));
        return in_array($name, $allowed, true);
    }
}