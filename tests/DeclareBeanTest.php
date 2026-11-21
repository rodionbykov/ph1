<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;
use TestFixtures\DeclareBean\Item;
use TestFixtures\DeclareBean\MyConfig;

require_once __DIR__ . '/fixtures/DeclareBeanFixtures.php';

final class DeclareBeanTest extends TestCase
{
    protected function setUp(): void
    {
        Item::resetCount();
    }

    public function testDeclareSingleton(): void
    {
        $factory = (new Ioc(''))
            ->declare('foo')->instanceOf(Item::class)->done();

        $item1 = $factory->getBean('foo');
        $this->assertSame(1, Item::count());
        $item2 = $factory->getBean('foo');
        $this->assertSame(1, Item::count());
        $this->assertSame($item1, $item2);
    }

    public function testDeclareTransient(): void
    {
        $factory = (new Ioc(''))
            ->declare('foo')->instanceOf(Item::class)->asTransient()->done();

        $item1 = $factory->getBean('foo');
        $this->assertSame(1, Item::count());
        $item2 = $factory->getBean('foo');
        $this->assertSame(2, Item::count());
        $this->assertNotSame($item1, $item2);
    }

    public function testDeclareSingletonWithOverride(): void
    {
        $factory = (new Ioc(''))
            ->declare('foo')->instanceOf(Item::class)->withOverrides(['start' => 100])->done();

        $item1 = $factory->getBean('foo');
        $this->assertSame(101, Item::count());
        $item2 = $factory->getBean('foo');
        $this->assertSame(101, Item::count());
        $this->assertSame($item1, $item2);
    }

    public function testDeclareTransientWithOverride(): void
    {
        $factory = (new Ioc(''))
            ->declare('foo')->instanceOf(Item::class)->asTransient()->withOverrides(['start' => 100])->done();

        $item1 = $factory->getBean('foo');
        $this->assertSame(101, Item::count());
        $item2 = $factory->getBean('foo');
        $this->assertSame(102, Item::count());
        $this->assertNotSame($item1, $item2);
    }

    public function testDeclareAndAdd(): void
    {
        $factory = (new Ioc('', ['omitTypedProperties' => false]))
            ->declare('foo')->instanceOf(MyConfig::class)->done()
            ->declare('name')->asValue('test')->done()
            ->declare('config')->asValue('some')->done();

        $item = $factory->getBean('foo');
        $this->assertSame('test', $item->getName());
        $this->assertSame('some', $item->getConfig());
    }

    public function testDeclareWithOverride(): void
    {
        $factory = (new Ioc('', ['omitTypedProperties' => false]))
            ->declare('foo')->instanceOf(MyConfig::class)
            ->withOverrides(['name' => 'test', 'config' => 'some'])->done()
            ->addBean('name', 'not-test')->addBean('config', 'config');

        $item = $factory->getBean('foo');
        $this->assertSame('test', $item->getName());
        $this->assertSame('some', $item->getConfig());
    }

    public function testDeclareInteractWithDefault(): void
    {
        $factory = (new Ioc('', ['omitDefaultedProperties' => false]))
            ->declareBean('foo', MyConfig::class)
            ->addBean('dftname', 'injected');
        $this->assertSame('injected', $factory->getBean('foo')->getDftName());

        $factory = (new Ioc('', ['omitDefaultedProperties' => true]))
            ->declareBean('foo', MyConfig::class)
            ->addBean('dftname', 'injected');
        $this->assertSame('default', $factory->getBean('foo')->getDftName());
    }
}
