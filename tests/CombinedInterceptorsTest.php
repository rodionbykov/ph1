<?php

declare(strict_types=1);

namespace Tests;

use Framework\Aop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TestFixtures\Aop\AdvancedReverseService;
use TestFixtures\Aop\AfterInterceptor;
use TestFixtures\Aop\AroundInterceptor;
use TestFixtures\Aop\ArrayService;
use TestFixtures\Aop\BeforeInterceptor;
use TestFixtures\Aop\ErrorInterceptor;
use TestFixtures\Aop\MethodRecordingInterceptor;
use TestFixtures\Aop\ReverseService;
use TestFixtures\Aop\StackLogService;
use TestFixtures\Aop\StringService;
use TestFixtures\Issue518\Daos\AdvReverse as DaoAdvReverse;
use TestFixtures\Issue518\Services\AdvReverse as ServiceAdvReverse;

require_once __DIR__ . '/fixtures/AopFixtures.php';
require_once __DIR__ . '/fixtures/Issue518Fixtures.php';

/**
 * Ports CombinedInterceptorsTest.cfc and CombinedInterceptorsTestIssue518.cfc.
 */
final class CombinedInterceptorsTest extends TestCase
{
    public static function layouts(): array
    {
        return [
            'aop services' => [false],
            'issue 518' => [true],
        ];
    }

    #[DataProvider('layouts')]
    public function testBeforeAroundAfterInterception(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        $factory->intercept('ReverseService', 'beforeInterceptor');
        $factory->intercept('ReverseService', 'aroundInterceptor');
        $factory->intercept('ReverseService', 'afterInterceptor');

        $this->assertSame('around,' . strrev('beforeHello!') . ',around', $factory->getBean('reverseService')->doReverse('Hello!'));
        $this->assertSame(['before', 'around', 'doReverse', 'after'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testInitMethods(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518, true);
        $setter = $issue518 ? 'setStackLog' : 'setStackLogService';
        $factory->intercept('advReverse', 'beforeInterceptor');
        $factory->getBean('advReverseService')->doWrap('Hello!');

        $this->assertSame(
            ['init', $setter, 'configure', 'before', 'doWrap', 'before', 'doFront', 'before', 'doRear'],
            $log->getStack()
        );

        [$factory, $log] = $this->createFactory($issue518, true);
        $factory->intercept('advReverse', 'beforeInterceptor', "init,configure,{$setter},doWrap");
        $factory->getBean('advReverseService')->doWrap('Hello!');

        $this->assertSame(
            ['before', 'init', 'before', $setter, 'before', 'configure', 'before', 'doWrap', 'doFront', 'doRear'],
            $log->getStack()
        );
    }

    #[DataProvider('layouts')]
    public function testInterceptOnRegex(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518, true);
        $factory->intercept('/^reverse.*$/', 'beforeInterceptor');

        $advanced = $factory->getBean('advReverseService');
        $reverse = $factory->getBean('reverse');
        $array = $factory->getBean('array');
        $this->assertSame('front-Hello!-rear', $advanced->doWrap('Hello!'));
        $this->assertSame(strrev('beforeHello!'), $reverse->doReverse('Hello!'));
        $this->assertSame(['dog', 'cat', 'mouse'], $array->doListToArray('dog,cat,mouse'));
        $this->assertSame(
            ['init', $issue518 ? 'setStackLog' : 'setStackLogService', 'configure', 'doWrap', 'doFront', 'doRear', 'before', 'doReverse', 'doListToArray'],
            $log->getStack()
        );
    }

    #[DataProvider('layouts')]
    public function testInterceptOnType(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518, true);
        $factory->interceptByType(StringService::class, 'beforeInterceptor', 'doReverse,doForward,doWrap');

        $advanced = $factory->getBean('advReverseService');
        $reverse = $factory->getBean('reverse');
        $array = $factory->getBean('array');
        $this->assertSame('front-beforeHello!-rear', $advanced->doWrap('Hello!'));
        $this->assertSame(strrev('beforeHello!'), $reverse->doReverse('Hello!'));
        $this->assertSame(['dog', 'cat', 'mouse'], $array->doListToArray('dog,cat,mouse'));
        $this->assertSame(
            ['init', $issue518 ? 'setStackLog' : 'setStackLogService', 'configure', 'before', 'doWrap', 'doFront', 'doRear', 'before', 'doReverse', 'doListToArray'],
            $log->getStack()
        );
    }

    #[DataProvider('layouts')]
    public function testMultipleBeforeInterceptors(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        foreach (['beforeA', 'beforeB', 'beforeC'] as $name) {
            $factory->declareBean($name, BeforeInterceptor::class, true, ['name' => $name]);
            $factory->intercept('reverseService', $name);
        }

        $this->assertSame(strrev('beforebeforebeforeHello!'), $factory->getBean('reverseService')->doReverse('Hello!'));
        $this->assertSame(['beforeA', 'beforeB', 'beforeC', 'doReverse'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testMultipleAfterInterceptors(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        foreach (['afterA', 'afterAlterResultB', 'afterC'] as $name) {
            $factory->declareBean($name, AfterInterceptor::class, true, ['name' => $name]);
            $factory->intercept('reverseService', $name);
        }

        $this->assertSame(strrev('Hello!') . ',afterAlterResultB', $factory->getBean('reverseService')->doReverse('Hello!'));
        $this->assertSame(['doReverse', 'afterA', 'afterAlterResultB', 'afterC'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testMultipleAroundInterceptors(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        foreach (['aroundA', 'aroundB', 'aroundC'] as $name) {
            $factory->declareBean($name, AroundInterceptor::class, true, ['name' => $name]);
            $factory->intercept('reverseService', $name);
        }

        $this->assertSame('aroundA,aroundB,aroundC,!olleH,aroundC,aroundB,aroundA', $factory->getBean('reverseService')->doReverse('Hello!'));
        $this->assertSame(['aroundA', 'aroundB', 'aroundC', 'doReverse'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testMethodMatches(bool $issue518): void
    {
        foreach (['doReverse' => false, '' => true, '*' => true, 'doReverse,' => false, 'doReverse,doForward' => true] as $methods => $expected) {
            [$factory, $log] = $this->createFactory($issue518);
            $factory->intercept('reverseService', 'beforeInterceptor', $methods);
            $factory->getBean('reverseService')->doForward('Hello!');
            $this->assertSame($expected ? ['before', 'doForward'] : ['doForward'], $log->getStack());
        }
    }

    #[DataProvider('layouts')]
    public function testNamedMethodInterceptions(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        $factory->intercept('ReverseService', 'beforeInterceptor', 'doReverse');
        $reverse = $factory->getBean('reverseService');

        $this->assertSame(strrev('beforeHello!'), $reverse->doReverse('Hello!'));
        $this->assertSame('Hello!', $reverse->doForward('Hello!'));
        $this->assertSame(strrev('beforeHello!'), $reverse->doReverse('Hello!'));
        $this->assertSame(['before', 'doReverse', 'doForward', 'before', 'doReverse'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testOnErrorInterceptors(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        $this->assertSame('Hello!', $factory->getBean('reverseService')->doForward('Hello!'));
        $this->assertSame(['doForward'], $log->getStack());

        [$factory, $log] = $this->createFactory($issue518);
        $factory->intercept('reverseService', 'errorInterceptor', 'throwError');
        try {
            $factory->getBean('reverseService')->throwError();
            $this->fail('The intercepted exception must be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('I AM AN EVIL ERROR YOU WANT TO TRAP!', $exception->getMessage());
        }
        $this->assertSame(['throwError', 'onError'], $log->getStack());
    }

    #[DataProvider('layouts')]
    public function testPrivateMethodInterceptors(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518, true);
        $factory->intercept($issue518 ? 'advReverseService' : 'advReverse', 'beforeInterceptor', 'doFront');

        $this->assertSame('front-beforeHello!-rear', $factory->getBean('advReverseService')->doWrap('Hello!'));
        $this->assertSame(
            ['init', $issue518 ? 'setStackLog' : 'setStackLogService', 'configure', 'doWrap', 'before', 'doFront', 'doRear'],
            $log->getStack()
        );
    }

    #[DataProvider('layouts')]
    public function testSingleInterceptorOnMultipleObjects(bool $issue518): void
    {
        [$factory, $log] = $this->createFactory($issue518);
        $factory->declareBean('aroundA', AroundInterceptor::class, true, ['name' => 'aroundA']);
        $factory->intercept('advReverse', 'aroundA', 'doWrap');
        $factory->intercept('ReverseService', 'aroundA', 'doReverse');
        $reverse = $factory->getBean('reverseService');
        $advanced = $factory->getBean('advReverseService');

        $this->assertSame('aroundA,front-aroundA,!olleH,aroundA-rear,aroundA', $advanced->doWrap($reverse->doReverse('Hello!')));
        $this->assertSame(
            ['init', $issue518 ? 'setStackLog' : 'setStackLogService', 'aroundA', 'doReverse', 'aroundA', 'doWrap', 'doFront', 'doRear'],
            $log->getStack()
        );
    }

    public function testNestedInterceptedCallsRetainTheirOwnMethodNames(): void
    {
        [$factory, $log] = $this->createFactory(false);
        $factory->declareBean('recordingInterceptor', MethodRecordingInterceptor::class);
        $factory->intercept('advReverse', 'recordingInterceptor');
        $advanced = $factory->getBean('advReverse');
        $log->reset();

        $this->assertSame('front-Hello!-rear', $advanced->doWrap('Hello!'));
        $this->assertSame(
            ['enter:doWrap', 'doWrap', 'enter:doFront', 'doFront', 'exit:doFront', 'enter:doRear', 'doRear', 'exit:doRear', 'exit:doWrap'],
            $log->getStack()
        );
    }

    public function testBeanAndMethodNamesMatchCaseInsensitively(): void
    {
        [$factory, $log] = $this->createFactory(false);
        $factory->intercept('REVERSESERVICE', 'beforeInterceptor', 'doreverse');

        $this->assertSame(strrev('beforeHello!'), $factory->getBean('reverseService')->doReverse('Hello!'));
        $this->assertSame(['before', 'doReverse'], $log->getStack());
    }

    public function testSameClassDeclarationsAreIndependentAndAliasesShareInstances(): void
    {
        [$factory, $log] = $this->createFactory(false);
        $factory->declareBean('otherReverse', ReverseService::class);
        $factory->intercept('reverseService', 'beforeInterceptor');

        $reverse = $factory->getBean('reverseService');
        $this->assertSame($reverse, $factory->getBean('reverse'));
        $this->assertNotSame($reverse, $factory->getBean('otherReverse'));
        $this->assertSame('!olleH', $factory->getBean('otherReverse')->doReverse('Hello!'));
        $this->assertSame(['doReverse'], $log->getStack());
    }

    public function testIssue518DoesNotInterceptDaoWithTheSameShortClassName(): void
    {
        [$factory, $log] = $this->createFactory(true);
        $factory->intercept('advReverse', 'beforeInterceptor', 'doWrap');
        $dao = $factory->getBean('advReverseDao');
        $log->reset();

        $this->assertSame('wrong-doWrap', $dao->doWrap('Hello!'));
        $this->assertSame([], $log->getStack());
        $this->assertSame('front-beforeHello!-rear', $factory->getBean('advReverseService')->doWrap('Hello!'));
    }

    private function createFactory(bool $issue518, bool $configure = false): array
    {
        $config = ['beanInitMethod' => 'init'];
        if ($configure) {
            $config['initMethod'] = 'configure';
        }
        $factory = new Aop([], $config);
        $log = new StackLogService();
        $factory->addBean('callStack', $log);
        $factory->addBean('stackLogService', $log);
        if ($issue518) {
            $factory->addBean('stackLog', $log);
        }
        $factory->declareBean('reverseService', ReverseService::class);
        $factory->addAlias('reverse', 'reverseService');
        $factory->declareBean('advReverse', $issue518 ? ServiceAdvReverse::class : AdvancedReverseService::class);
        $factory->addAlias('advReverseService', 'advReverse');
        if ($issue518) {
            $factory->declareBean('advReverseDao', DaoAdvReverse::class);
        }
        $factory->declareBean('array', ArrayService::class);
        $factory->declareBean('beforeInterceptor', BeforeInterceptor::class);
        $factory->declareBean('afterInterceptor', AfterInterceptor::class);
        $factory->declareBean('aroundInterceptor', AroundInterceptor::class);
        $factory->declareBean('errorInterceptor', ErrorInterceptor::class);

        return [$factory, $log];
    }
}
