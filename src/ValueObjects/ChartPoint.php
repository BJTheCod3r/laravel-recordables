<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class ChartPoint implements Arrayable, JsonSerializable
{
    public function __construct(
        public CarbonImmutable $x,
        public int|float $y,
    ) {}

    public function toArray(): array
    {
        return [
            'x' => $this->x->toIso8601String(),
            'y' => $this->y,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
