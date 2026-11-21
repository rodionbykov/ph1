<?php

declare(strict_types=1);

namespace Tests;

use Framework\Ioc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConstantTest extends TestCase
{
    private Ioc $constants;

    protected function setUp(): void
    {
        $this->constants = new Ioc('', ['constants' => ['known' => 42]]);
    }

    public function testHaveKnownValue(): void
    {
        $this->assertSame(42, $this->constants->getBean('known'));
    }

    public function testBeSingleton(): void
    {
        $this->assertTrue($this->constants->isSingleton('known'));
    }

    public function testHaveKnownMetadata(): void
    {
        $info = $this->constants->getBeanInfo('known');

        $this->assertSame(42, $info['value']);
        $this->assertTrue($info['isSingleton']);
    }

    public static function folderLists(): array
    {
        return [
            'comma-separated' => [' , model/services, , '],
            'array' => [['', 'model/services', '  ']],
        ];
    }

    #[DataProvider('folderLists')]
    public function testEmptyFoldersAreIgnoredWithoutSkippingValidFolders(array|string $folders): void
    {
        $factory = new Ioc($folders, ['constants' => ['known' => 42]]);

        $this->assertSame(42, $factory->getBean('known'));
        $this->assertTrue($factory->containsBean('formatter'));
    }
}
