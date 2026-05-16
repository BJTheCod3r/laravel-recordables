<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

final class History implements IteratorAggregate, Countable, Arrayable, JsonSerializable
{
    /**
     * @param  array<int, HistoryPoint>  $points
     */
    public function __construct(
        public readonly string $metric,
        private readonly array $points,
    ) {}

    /** @return array<int, HistoryPoint> */
    public function all(): array
    {
        return $this->points;
    }

    public function first(): ?HistoryPoint
    {
        return $this->points[0] ?? null;
    }

    public function last(): ?HistoryPoint
    {
        $count = count($this->points);

        return $count === 0 ? null : $this->points[$count - 1];
    }

    /** @return array<int, int|float> */
    public function values(): array
    {
        return array_map(fn (HistoryPoint $p) => $p->value, $this->points);
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
        return array_map(fn (HistoryPoint $p) => $p->toArray(), $this->points);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
