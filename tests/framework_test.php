<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Framework\Aop;
use Framework\Inject;
use Framework\Ioc;

require __DIR__ . '/fixtures/TestFixtures.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$ioc = new Ioc([], []);
$ioc->declareBean('clockService', TestFixtures\ClockService::class);
$ioc->declareBean('needsClockService', TestFixtures\NeedsClockService::class);
$ioc->declareBean('instant', TestFixtures\InstantBean::class, false);
$ioc->declareBean('namedGreeting', TestFixtures\GreetingService::class, true, ['prefix' => 'hello']);

$clockA = $ioc->getBean('clockService');
$clockB = $ioc->getBean('clockService');
assertTrue($clockA === $clockB, 'Singleton beans should be cached.');

$instantA = $ioc->getBean('instant');
$instantB = $ioc->getBean('instant');
assertTrue($instantA !== $instantB, 'Transient beans should not be cached.');

$needsClock = $ioc->getBean('needsClockService');
assertSame('clock', $needsClock->clockName(), 'Property injection should wire singleton dependencies.');

$namedGreeting = $ioc->getBean('namedGreeting');
assertSame('hello world', $namedGreeting->greet('world'), 'Constructor overrides should be honored for declared beans.');

$aop = new Aop([], []);
$aop->declareBean('eventLogService', TestFixtures\EventLogService::class);
$aop->declareBean('mathService', TestFixtures\MathService::class);
$aop->declareBean('loggingInterceptor', TestFixtures\LoggingInterceptor::class);
$aop->intercept('mathService', 'loggingInterceptor', 'double,explode');

$math = $aop->getBean('mathService');
assertSame(6, $math->double(3), 'AOP before/after interception should preserve and augment return values.');

$events = $aop->getBean('eventLogService')->events();
assertSame(['before:double', 'after:double:6'], $events, 'AOP interceptors should run around successful calls in order.');

try {
    $math->explode();
    assertTrue(false, 'AOP should rethrow intercepted exceptions.');
} catch (RuntimeException $exception) {
    assertSame('boom', $exception->getMessage(), 'AOP should preserve intercepted exceptions.');
}

$events = $aop->getBean('eventLogService')->events();
assertSame(
    ['before:double', 'after:double:6', 'before:explode', 'error:explode:boom'],
    $events,
    'AOP onError interception should record failed calls.'
);

fwrite(STDOUT, "framework tests passed\n");