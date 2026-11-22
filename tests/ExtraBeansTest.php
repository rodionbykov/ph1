<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\TestCase;
use TestFixtures\ExtraBeans\Sheep\Item;
use TestFixtures\Model\Fish\User;

require_once __DIR__ . '/fixtures/extrabeans/sheep/Item.php';
require_once __DIR__ . '/fixtures/model/fish/User.php';

/**
 * PHPUnit port of cfml_tests/ExtraBeansTest.cfc.
 */
final class ExtraBeansTest extends TestCase
{
    protected function setUp(): void
    {
        Item::resetCount();
    }

    public function testResolveMapping(): void
    {
        $factory = new Ioc('tests/fixtures/extrabeans');

        $this->assertTrue($factory->containsBean('item'));
        $this->assertTrue($factory->containsBean('itemSheep'));

        $item1 = $factory->getBean('item');
        $item2 = $factory->getBean('itemSheep');

        $this->assertSame($item1, $item2);
        $this->assertSame(1, Item::count());
    }

    public function testResolveMappingWithSingular(): void
    {
        $factory = new Ioc(
            'tests/fixtures/extrabeans',
            ['singulars' => ['sheep' => 'bean']]
        );

        $this->assertTrue($factory->containsBean('item'));
        $this->assertTrue($factory->containsBean('itemBean'));

        $item1 = $factory->getBean('item');
        $item2 = $factory->getBean('itemBean');

        $this->assertNotSame($item1, $item2);
        $this->assertSame(2, Item::count());
    }

    public function testNotInjectTypedProperty(): void
    {
        $factory = new Ioc(
            ['tests/fixtures/model', 'tests/fixtures/extrabeans'],
            [
                'singulars' => ['sheep' => 'lamb'],
                'omitTypedProperties' => true,
            ]
        );

        $this->assertTrue($factory->containsBean('item'));
        $this->assertTrue($factory->isSingleton('item'));
        $this->assertTrue($factory->containsBean('itemLamb'));
        $this->assertTrue($factory->isSingleton('itemLamb'));

        $user = $factory->getBean('user');
        $this->assertInstanceOf(User::class, $user);

        $item = $user->itemTest();
        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame(1, Item::count());

        $this->assertNull($user->getItemLamb());
        $this->assertSame(1, Item::count());
    }
}
