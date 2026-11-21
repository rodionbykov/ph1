<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;
use TestFixtures\Circular\A;
use TestFixtures\Circular\B;

require_once __DIR__ . '/fixtures/CircularFixtures.php';

final class CircularTest extends TestCase
{
    public function testResolveCircularDependencies(): void
    {
        $factory = $this->createFactory();

        $a = $factory->getBean('a');
        $b = $factory->getBean('b');

        $this->assertSame($a->id, $b->a->id);
        $this->assertSame($b->id, $a->b->id);
        $this->assertFalse(property_exists($a, 'name'));
        $this->assertFalse(property_exists($b, 'name'));
    }

    public function testResolveAndConfigureCircularDependencies(): void
    {
        $factory = $this->createFactory('configure');

        $a = $factory->getBean('a');
        $b = $factory->getBean('b');

        $this->assertSame($a->id, $b->a->id);
        $this->assertSame($b->id, $a->b->id);
        $this->assertSame('A', $a->name);
        $this->assertSame('B', $b->name);
    }

    private function createFactory(?string $initMethod = null): Ioc
    {
        $config = $initMethod === null ? [] : ['initMethod' => $initMethod];
        $factory = new Ioc([], $config);
        $factory->declareBean('a', A::class);
        $factory->declareBean('b', B::class);

        return $factory;
    }
}
