<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit port of cfml_tests/EmptyTest.cfc.
 */
final class EmptyTest extends TestCase
{
    private Ioc $emptyFactory;

    protected function setUp(): void
    {
        $this->emptyFactory = new Ioc('');
    }

    public function testContainBeanFactory(): void
    {
        $this->assertTrue($this->emptyFactory->containsBean('beanFactory'));
    }

    public function testContainJustOneBean(): void
    {
        $info = $this->emptyFactory->getBeanInfo();

        $this->assertCount(1, $info['beanInfo']);
        $this->assertSame(['beanFactory'], array_keys($info['beanInfo']));
    }
}
