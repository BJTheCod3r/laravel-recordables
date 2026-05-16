<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class MinMax implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $metric,
        public int|float $min,
        public int|float $max,
        public CarbonImmutable $minRecordedAt,
        public CarbonImmutable $maxRecordedAt,
    ) {}

    public function range(): int|float
    {
        return $this->max - $this->min;
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'min' => $this->min,
            'max' => $this->max,
            'range' => $this->range(),
            'min_recorded_at' => $this->minRecordedAt->toIso8601String(),
            'max_recorded_at' => $this->maxRecordedAt->toIso8601String(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
