<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

final class ChartData implements IteratorAggregate, Countable, Arrayable, JsonSerializable
{
    /**
     * @param  array<int, ChartPoint>  $points
     */
    public function __construct(
        public readonly string $metric,
        public readonly string $period,
        private readonly array $points,
    ) {}

    /** @return array<int, ChartPoint> */
    public function points(): array
    {
        return $this->points;
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->points);
    }

    public function toArray(): array
    {
        return array_map(fn (ChartPoint $p) => $p->toArray(), $this->points);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
