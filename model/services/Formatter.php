<?php

declare(strict_types=1);

namespace App\Model\Services;

use DateTimeInterface;

final class Formatter
{
    public function longdate(DateTimeInterface $when): string
    {
        return $when->format('F j, Y') . ' at ' . $when->format('g:i:s A T');
    }
}