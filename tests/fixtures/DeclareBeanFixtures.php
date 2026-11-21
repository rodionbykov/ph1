<?php

declare(strict_types=1);

namespace TestFixtures\DeclareBean;

final class Item
{
    private static ?int $count = null;
    public readonly int $itemNumber;

    public function __construct(int $start = 0)
    {
        self::$count ??= $start;
        $this->itemNumber = ++self::$count;
    }

    public static function resetCount(): void
    {
        self::$count = null;
    }

    public static function count(): ?int
    {
        return self::$count;
    }
}

final class MyConfig
{
    private string $name;
    private string $dftname = 'default';
    private string $config;

    public function __construct(string $data = 'none')
    {
        $this->setConfig($data);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDftName(): string
    {
        return $this->dftname;
    }

    public function setConfig(string $config): void
    {
        $this->config = $config;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}
