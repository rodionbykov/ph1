<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;

final class AddBeanTest extends TestCase
{
    private Ioc $added;

    protected function setUp(): void
    {
        $this->added = (new Ioc([], []))
            ->declare('known')
            ->asValue(42)
            ->done();
    }

    public function testHaveKnownValue(): void
    {
        $this->assertSame(42, $this->added->getBean('known'));
    }

    public function testBeSingleton(): void
    {
        $this->assertTrue($this->added->isSingleton('known'));
    }

    public function testHaveKnownMetadata(): void
    {
        $info = $this->added->getBeanInfo('known');

        $this->assertSame(42, $info['value']);
        $this->assertTrue($info['isSingleton']);
    }
}
