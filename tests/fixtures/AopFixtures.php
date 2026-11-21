<?php

declare(strict_types=1);

/**
 * PHP fixtures mirroring the CFML components under cfml_tests/aop/.
 *
 * In the CFML tests the beans are discovered from the /tests/aop/services and
 * /tests/aop/interceptors folders. The PHP port registers the same beans
 * explicitly via Ioc::declareBean() because folder discovery only understands
 * the controllers/model layout.
 */

namespace TestFixtures\Aop;

use Framework\Inject;
use Framework\InterceptsInternalCalls;
use Framework\InternalCallAware;
use RuntimeException;

/**
 * Port of aop/services/stackLogService.cfc.
 *
 * Records every logged call so tests can assert interceptor/method ordering,
 * standing in for the CFML `request.callstack` array.
 */
final class StackLogService
{
    private array $stack = [];

    public function log(string $message): void
    {
        $this->stack[] = $message;
    }

    public function getStack(): array
    {
        return $this->stack;
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}

/**
 * Port of aop/services/Reverse.cfc (which extends stringService extends service).
 */
abstract class StringService
{
    #[Inject]
    protected StackLogService $stackLogService;
}

class ReverseService extends StringService
{

    public function doForward(string $input): string
    {
        $this->stackLogService->log('doForward');
        return strrev(strrev($input));
    }

    public function doReverse(string $input): string
    {
        $this->stackLogService->log('doReverse');
        return strrev($input);
    }

    public function throwError(): never
    {
        $this->stackLogService->log('throwError');
        throw new RuntimeException('I AM AN EVIL ERROR YOU WANT TO TRAP!');
    }
}

/**
 * Port of aop/interceptors/aop/BeforeInterceptor.cfc.
 *
 * The CFML interceptor mutates `arguments.args` before the target runs. The
 * PHP BeanProxy forwards the argument array by value, so the parameter is
 * declared by reference (`array &$args`) to allow the same mutation.
 */
final class BeforeInterceptor
{
    #[Inject]
    private StackLogService $stackLogService;

    public function __construct(private string $name = 'before')
    {
    }

    public function before(object $target, string $method, array &$args): void
    {
        $this->stackLogService->log($this->name);

        // CFML forwards named arguments (args.input); PHP's __call forwards a
        // positional argument list, so the first parameter is index 0.
        if (array_key_exists(0, $args) && is_string($args[0])) {
            $args[0] = 'before' . $args[0];
        }
    }
}

/**
 * Port of aop/interceptors/aop/AfterInterceptor.cfc (default name "after").
 */
final class AfterInterceptor
{
    #[Inject]
    private StackLogService $stackLogService;

    public function __construct(private string $name = 'after')
    {
    }

    public function after(object $target, string $method, array $args, mixed $result = null): mixed
    {
        $this->stackLogService->log($this->name);

        return stripos($this->name, 'alter') !== false && $result !== null
            ? $result . ',' . $this->name
            : $result;
    }
}

/**
 * Port of aop/interceptors/aop/AroundInterceptor.cfc (default name "around").
 */
final class AroundInterceptor
{
    #[Inject]
    private StackLogService $stackLogService;

    public function __construct(private string $name = 'around')
    {
    }

    public function around(object $target, string $method, array $args, callable $proceed): mixed
    {
        $this->stackLogService->log($this->name);

        $result = $proceed($args);

        if (is_string($result)) {
            return $this->name . ',' . $result . ',' . $this->name;
        }

        return $result;
    }
}

/**
 * Port of aop/interceptors/aop/ErrorInterceptor.cfc.
 */
final class ErrorInterceptor
{
    #[Inject]
    private StackLogService $stackLogService;

    public function onError(object $target, string $method, array $args, \Throwable $exception): void
    {
        $this->stackLogService->log('onError');
    }
}

final class MethodRecordingInterceptor
{
    #[Inject]
    private StackLogService $stackLogService;

    public function around(object $target, string $method, array $args, callable $proceed): mixed
    {
        $this->stackLogService->log('enter:' . $method);
        $result = $proceed($args);
        $this->stackLogService->log('exit:' . $method);
        return $result;
    }
}

abstract class AdvancedReverse extends ReverseService implements InternalCallAware
{
    use InterceptsInternalCalls;

    public function __construct(private StackLogService $callStack)
    {
    }

    public function init(): void
    {
        $this->callStack->log('init');
    }

    public function configure(): void
    {
        $this->stackLogService->log('configure');
    }

    public function doWrap(string $input): string
    {
        $this->stackLogService->log('doWrap');
        $front = $this->interceptInternalCall('doFront', [$input], $this->doFront(...));
        return $this->interceptInternalCall('doRear', [$front], $this->doRear(...));
    }

    private function doFront(string $input): string
    {
        $this->stackLogService->log('doFront');
        return 'front-' . $input;
    }

    private function doRear(string $input): string
    {
        $this->stackLogService->log('doRear');
        return $input . '-rear';
    }
}

final class AdvancedReverseService extends AdvancedReverse
{
    public function setStackLogService(StackLogService $stackLogService): void
    {
        $stackLogService->log('setStackLogService');
        $this->stackLogService = $stackLogService;
    }
}

final class ArrayService
{
    #[Inject]
    private StackLogService $stackLogService;

    public function doListToArray(string $list): array
    {
        $this->stackLogService->log('doListToArray');
        return explode(',', $list);
    }
}
