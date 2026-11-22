<?php

declare(strict_types=1);

namespace TestFixtures\ExtraBeans\Sheep;

final class Item
{
    private static int $count = 0;
    public readonly int $itemNumber;

    public function __construct()
    {
        $this->itemNumber = ++self::$count;
    }

    public static function resetCount(): void
    {
        self::$count = 0;
    }

    public static function count(): int
    {
        return self::$count;
    }
}
