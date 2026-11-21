<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;
use TestFixtures\Defaults\DefaultProperties;

require_once __DIR__ . '/fixtures/DefaultFixtures.php';

final class DefaultPropertyTest extends TestCase
{
    public function testHaveDefaultValue(): void
    {
        $factory = (new Ioc(''))
            ->declare('default')->instanceOf(DefaultProperties::class)->asTransient()->done();

        foreach ([new DefaultProperties(), $factory->getBean('default')] as $item) {
            $this->assertNull($item->getSimple());
            $this->assertNull($item->getTyped());
            $this->assertSame('Default Value', $item->getDefaulted());
            $this->assertSame('Default Type Value', $item->getDefaultedType());
        }
    }
}
