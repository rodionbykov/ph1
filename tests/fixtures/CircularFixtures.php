<?php

declare(strict_types=1);

namespace TestFixtures\Circular;

use Framework\Inject;

#[\AllowDynamicProperties]
final class A
{
    #[Inject]
    public ?B $b = null;

    public int $id;

    public function __construct()
    {
        $this->id = spl_object_id($this);
    }

    public function configure(): void
    {
        $this->name = 'A';
    }
}

#[\AllowDynamicProperties]
final class B
{
    #[Inject]
    public ?A $a = null;

    public int $id;

    public function __construct()
    {
        $this->id = spl_object_id($this);
    }

    public function configure(): void
    {
        $this->name = 'B';
    }
}
