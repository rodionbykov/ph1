<?php

declare(strict_types=1);

namespace Framework;

final class RendererBuilder
{
    public function __construct(private array &$renderData)
    {
    }

    public function type(string|callable $value): self
    {
        $this->renderData['type'] = $value;
        return $this;
    }

    public function data(mixed $value): self
    {
        $this->renderData['data'] = $value;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->renderData['headers'][] = ['name' => $name, 'value' => $value];
        return $this;
    }

    public function statusCode(int $value): self
    {
        $this->renderData['statusCode'] = $value;
        return $this;
    }

    public function statusText(string $value): self
    {
        $this->renderData['statusText'] = $value;
        return $this;
    }

    public function jsonpCallback(string $value): self
    {
        $this->renderData['jsonpCallback'] = $value;
        return $this;
    }
}