<?php

declare(strict_types=1);

namespace Tests;

use Framework\Aop;
use PHPUnit\Framework\TestCase;
use TestFixtures\Aop\AfterInterceptor;
use TestFixtures\Aop\AroundInterceptor;
use TestFixtures\Aop\BeforeInterceptor;
use TestFixtures\Aop\ReverseService;
use TestFixtures\Aop\StackLogService;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/fixtures/AopFixtures.php';

/**
 * PHPUnit port of cfml_tests/BasicBeanTest.cfc.
 *
 * The CFML test builds a framework.aop with the aop/services (and optionally
 * aop/interceptors) folders and interceptor advice against ReverseService.
 */
final class BasicBeanTest extends TestCase
{
    private Aop $factory;

    protected function setUp(): void
    {
        $this->factory = new Aop([], []);
        $this->factory->declareBean('stackLogService', StackLogService::class);
        $this->factory->declareBean('reverseService', ReverseService::class);
        $this->factory->declareBean('beforeInterceptor', BeforeInterceptor::class);
        $this->factory->declareBean('afterInterceptor', AfterInterceptor::class);
        $this->factory->declareBean('aroundInterceptor', AroundInterceptor::class);
    }

    private function callstack(): array
    {
        return $this->factory->getBean('stackLogService')->getStack();
    }

    public function testNoInterceptors(): void
    {
        $reverser = $this->factory->getBean('reverseService');

        $result = $reverser->doReverse('Hello!');

        $this->assertSame('!olleH', $result);
        $this->assertCount(1, $this->callstack());
        $this->assertSame(['doReverse'], $this->callstack());
    }

    public function testBeforeInterceptors(): void
    {
        $this->factory->intercept('reverseService', 'beforeInterceptor');

        $reverser = $this->factory->getBean('reverseService');
        $result = $reverser->doReverse('Hello!');

        $this->assertSame(strrev('beforeHello!'), $result, 'Before works');
        $this->assertCount(2, $this->callstack());
        $this->assertSame(['before', 'doReverse'], $this->callstack());
    }

    public function testAfterInterceptors(): void
    {
        $this->factory->intercept('reverseService', 'afterInterceptor');

        $reverser = $this->factory->getBean('reverseService');
        $result = $reverser->doReverse('Hello!');

        $this->assertSame(strrev('Hello!'), $result, 'Reverse still works');
        $this->assertCount(2, $this->callstack());
        $this->assertSame(['doReverse', 'after'], $this->callstack());
    }

    public function testAroundInterceptors(): void
    {
        $this->factory->intercept('reverseService', 'aroundInterceptor');

        $reverser = $this->factory->getBean('reverseService');
        $result = $reverser->doReverse('Hello!');

        $this->assertSame('around,' . strrev('Hello!') . ',around', $result, 'Called method through around');
        $this->assertCount(2, $this->callstack());
        $this->assertSame(['around', 'doReverse'], $this->callstack());
    }
}
