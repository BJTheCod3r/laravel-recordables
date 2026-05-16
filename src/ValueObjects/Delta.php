<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use BJTheCod3r\Recordables\Support\Percentage;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class Delta implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $metric,
        public int|float $previous,
        public int|float $current,
        public int|float $difference,
        public float $percentage,
    ) {}

    public static function between(string $metric, int|float $previous, int|float $current): self
    {
        return new self(
            metric: $metric,
            previous: $previous,
            current: $current,
            difference: $current - $previous,
            percentage: Percentage::change($previous, $current),
        );
    }

    public function isPositive(): bool
    {
        return $this->difference > 0;
    }

    public function isNegative(): bool
    {
        return $this->difference < 0;
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'previous' => $this->previous,
            'current' => $this->current,
            'difference' => $this->difference,
            'percentage' => $this->percentage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
