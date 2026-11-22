<?php

declare(strict_types=1);

namespace App\Model\Services;

final class Factory
{
    public function makeMeAnA(): string
    {
        return 'I am an A';
    }

    public function makeAWithFava(mixed $fava): string
    {
        $stamp = is_array($fava) ? ($fava['stamp'] ?? '') : $fava->stamp;
        return 'I am a ' . $stamp . ' bean';
    }
}
