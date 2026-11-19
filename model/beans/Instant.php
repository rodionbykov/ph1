<?php

declare(strict_types=1);

namespace App\Model\Beans;

use DateTimeImmutable;

final class Instant
{
    private DateTimeImmutable $when;

    public function __construct()
    {
        $this->when = new DateTimeImmutable();
    }

    public function created(): DateTimeImmutable
    {
        return $this->when;
    }
}