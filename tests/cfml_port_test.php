<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Framework\Facade;
use Framework\Ioc;
use Framework\One;

final class DeclaredCounterBean
{
    private static int $count = 0;

    public function __construct(private int $start = 0)
    {
        self::$count = $this->start + 1;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }

    public static function count(): int
    {
        return self::$count;
    }
}

final class FactoryProduct
{
    public function __construct(public string $value)
    {
    }
}

final class FactoryService
{
    public function makeMeAnA(): string
    {
        return 'I am an A';
    }

    public function makeAWithFava(array $favaBean): string
    {
        return 'I am a ' . $favaBean['stamp'] . ' bean';
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function runAddBeanTest(): void
{
    $added = (new Ioc([], []))
        ->declare('known')->asValue(42)->done();

    assertSame(42, $added->getBean('known'), 'AddBeanTest: declared value should be retrievable.');
    assertTrue($added->isSingleton('known'), 'AddBeanTest: declared values should be singleton beans.');

    $info = $added->getBeanInfo('known');
    assertSame(42, $info['value'], 'AddBeanTest: metadata should include the declared value.');
    assertTrue($info['isSingleton'], 'AddBeanTest: metadata should mark declared value as singleton.');
}

function runBeanInfoTest(): void
{
    $factory = new Ioc([], []);
    $info = $factory->getBeanInfo();
    assertTrue(is_array($info), 'BeanInfoTest: getBeanInfo() should return an array.');
    assertSame(['beanInfo'], array_keys($info), 'BeanInfoTest: top-level beanInfo key should exist.');

    $beanFactoryInfo = $factory->getBeanInfo('beanFactory');
    assertTrue(is_array($beanFactoryInfo), 'BeanInfoTest: beanFactory metadata should be an array.');
    assertTrue(array_key_exists('value', $beanFactoryInfo), 'BeanInfoTest: beanFactory metadata should include value.');
    assertTrue($beanFactoryInfo['isSingleton'], 'BeanInfoTest: beanFactory metadata should be singleton.');

    $parent = (new Ioc([], []))->declare('father')->asValue('figure')->done();
    $factory->setParent($parent);

    $nested = $factory->getBeanInfo();
    assertTrue(array_key_exists('parent', $nested), 'BeanInfoTest: nested parent metadata should be present.');
    assertTrue(array_key_exists('beanInfo', $nested['parent']), 'BeanInfoTest: parent beanInfo should be nested.');

    $flat = $factory->getBeanInfo('', true);
    assertFalse(array_key_exists('parent', $flat), 'BeanInfoTest: flattened bean info should not contain parent metadata.');
    assertTrue(array_key_exists('father', $flat['beanInfo']), 'BeanInfoTest: flattened bean info should include parent beans.');

    $factory->addBean('mother', 'figure');
    $regexInfo = $factory->getBeanInfo('', false, 'her$');
    assertTrue(array_key_exists('father', $regexInfo['beanInfo']), 'BeanInfoTest: regex filtering should include matching parent beans.');
    assertTrue(array_key_exists('mother', $regexInfo['beanInfo']), 'BeanInfoTest: regex filtering should include matching local beans.');

    $regexInfo = $factory->getBeanInfo('', false, '^f');
    assertTrue(array_key_exists('father', $regexInfo['beanInfo']), 'BeanInfoTest: regex filtering should retain matching beans.');
    assertFalse(array_key_exists('mother', $regexInfo['beanInfo']), 'BeanInfoTest: regex filtering should exclude non-matching beans.');
}

function runFrameworkFacadeTest(): void
{
    unset($GLOBALS['_fw1']);

    try {
        new Facade();
        assertTrue(false, 'frameworkFacadeTest: facade creation should fail outside an FW/1 request.');
    } catch (RuntimeException $exception) {
        assertSame('Unable to locate FW/1 for this request', $exception->getMessage(), 'frameworkFacadeTest: facade should report missing FW/1 request context.');
    }

    $fw = new One();
    $fw->onRequestStart('index.php');
    $facade = new Facade();

    assertTrue($facade->getBeanFactory() !== null, 'frameworkFacadeTest: facade should expose the active bean factory.');
    assertSame($fw->getFramework()['version'], $facade->getFramework()['version'], 'frameworkFacadeTest: facade should forward framework access.');
}

function runDeclareBeanTest(): void
{
    DeclaredCounterBean::reset();
    $factory = (new Ioc([], []))
        ->declare('foo')
        ->instanceOf(DeclaredCounterBean::class)
        ->done();

    $item1 = $factory->getBean('foo');
    assertSame(1, DeclaredCounterBean::count(), 'DeclareBeanTest: singleton declare should construct once.');
    $item2 = $factory->getBean('foo');
    assertSame(1, DeclaredCounterBean::count(), 'DeclareBeanTest: singleton declare should reuse the same instance.');
    assertTrue($item1 === $item2, 'DeclareBeanTest: singleton declare should cache the bean instance.');

    DeclaredCounterBean::reset();
    $factory = (new Ioc([], []))
        ->declare('foo')
        ->instanceOf(DeclaredCounterBean::class)
        ->asTransient()
        ->done();

    $item1 = $factory->getBean('foo');
    assertSame(1, DeclaredCounterBean::count(), 'DeclareBeanTest: transient declare should construct on first access.');
    $item2 = $factory->getBean('foo');
    assertSame(1, DeclaredCounterBean::count(), 'DeclareBeanTest: transient beans should not share static construction count assumptions.');
    assertTrue($item1 !== $item2, 'DeclareBeanTest: transient declare should not cache the bean instance.');

    DeclaredCounterBean::reset();
    $factory = (new Ioc([], []))
        ->declare('foo')
        ->instanceOf(DeclaredCounterBean::class)
        ->withOverrides(['start' => 100])
        ->done();

    $factory->getBean('foo');
    assertSame(101, DeclaredCounterBean::count(), 'DeclareBeanTest: overrides should feed declared constructor arguments.');
}

function runFactoryBeanTest(): void
{
    $factory = new Ioc([], []);
    $factory->declareBean('factory', FactoryService::class);
    $factory->declare('a')->fromFactory('factory', 'makeMeAnA');
    assertSame('I am an A', $factory->getBean('a'), 'FactoryBeanTest: factory bean methods should resolve by bean name.');

    $factory = new Ioc([], []);
    $factory->declare('a')->fromFactory(static fn (): string => 'I am an A');
    assertSame('I am an A', $factory->getBean('a'), 'FactoryBeanTest: closures should be usable as bean factories.');

    $factory = new Ioc([], []);
    $factory->declareBean('factory', FactoryService::class);
    $factory->addBean('favaBean', ['stamp' => 'fava']);
    $factory->declare('a')
        ->fromFactory('factory', 'makeAWithFava')
        ->withArguments(['favaBean']);
    assertSame('I am a fava bean', $factory->getBean('a'), 'FactoryBeanTest: factory arguments should resolve from beans.');

    $factory = new Ioc([], []);
    $factory->declareBean('factory', FactoryService::class);
    $factory->addBean('favaBean', ['stamp' => 'fava']);
    $factory->declare('a')
        ->fromFactory('factory', 'makeAWithFava')
        ->withArguments(['favaBean'])
        ->withOverrides(['favaBean' => ['stamp' => 'different']]);
    assertSame('I am a different bean', $factory->getBean('a'), 'FactoryBeanTest: local overrides should win over bean-provided factory arguments.');
}

runAddBeanTest();
runBeanInfoTest();
runFrameworkFacadeTest();
runDeclareBeanTest();
runFactoryBeanTest();

fwrite(STDOUT, "cfml port tests passed\n");