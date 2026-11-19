<?php

declare(strict_types=1);

namespace Framework;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Inject
{
    public function __construct(public ?string $bean = null)
    {
    }
}