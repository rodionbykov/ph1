<?php

declare(strict_types=1);

namespace TestFixtures\Defaults;

final class User37
{
    public function __construct(private string $dsn, private int $ID = 0)
    {
    }

    public function getDSN(): string
    {
        return $this->dsn;
    }

    public function getID(): int
    {
        return $this->ID;
    }
}

final class User37b
{
    private int $id;

    public function __construct(private string $dsn, int $cd = 0)
    {
        $this->id = $cd;
    }

    public function getDSN(): string
    {
        return $this->dsn;
    }

    public function getID(): int
    {
        return $this->id;
    }
}

final class User37c
{
    private string $userName;

    public function __construct(private string $dsn, private int $ID = 0, string $NAME = 'Bob')
    {
        $this->userName = $NAME;
    }

    public function getDSN(): string
    {
        return $this->dsn;
    }

    public function getID(): int
    {
        return $this->ID;
    }

    public function getName(): string
    {
        return $this->userName;
    }
}

final class DefaultProperties
{
    private mixed $simple = null;
    private ?string $typed = null;
    private string $defaulted = 'Default Value';
    private string $defaultedType = 'Default Type Value';

    public function getSimple(): mixed
    {
        return $this->simple;
    }

    public function getTyped(): ?string
    {
        return $this->typed;
    }

    public function getDefaulted(): string
    {
        return $this->defaulted;
    }

    public function getDefaultedType(): string
    {
        return $this->defaultedType;
    }
}
