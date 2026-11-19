<?php

declare(strict_types=1);

namespace TestFixtures;

use Framework\Inject;
use RuntimeException;

final class ClockService
{
    public function name(): string
    {
        return 'clock';
    }
}

final class NeedsClockService
{
    #[Inject]
    private ClockService $clockService;

    public function clockName(): string
    {
        return $this->clockService->name();
    }
}

final class InstantBean
{
}

final class GreetingService
{
    public function __construct(private string $prefix)
    {
    }

    public function greet(string $name): string
    {
        return $this->prefix . ' ' . $name;
    }
}

final class EventLogService
{
    private array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }

    public function events(): array
    {
        return $this->events;
    }
}

final class MathService
{
    public function double(int $value): int
    {
        return $value * 2;
    }

    public function explode(): never
    {
        throw new RuntimeException('boom');
    }
}

final class LoggingInterceptor
{
    #[Inject]
    private EventLogService $eventLogService;

    public function before(object $targetBean, string $methodName, array $args): void
    {
        $this->eventLogService->record('before:' . $methodName);
    }

    public function after(object $targetBean, string $methodName, array $args, mixed $result = null): void
    {
        $this->eventLogService->record('after:' . $methodName . ':' . (string) $result);
    }

    public function onError(object $targetBean, string $methodName, array $args, \Throwable $exception): void
    {
        $this->eventLogService->record('error:' . $methodName . ':' . $exception->getMessage());
    }
}