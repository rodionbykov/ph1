<?php

declare(strict_types=1);

namespace TestFixtures\Model\Fish;

final class User
{
    private $item;
    private ?string $itemLamb = null;

    public function __construct(private mixed $product = null)
    {
    }

    public function getProduct(): mixed
    {
        return $this->product;
    }

    public function itemTest(): mixed
    {
        return $this->item;
    }

    public function getItemLamb(): ?string
    {
        return $this->itemLamb;
    }
}
