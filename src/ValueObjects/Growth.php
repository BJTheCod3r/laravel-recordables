<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use BJTheCod3r\Recordables\Enums\Trend;
use BJTheCod3r\Recordables\Support\Percentage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class Growth implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $metric,
        public int|float $start,
        public int|float $end,
        public int|float $difference,
        public float $percentage,
        public CarbonImmutable $startRecordedAt,
        public CarbonImmutable $endRecordedAt,
    ) {}

    public static function between(string $metric, HistoryPoint $start, HistoryPoint $end): self
    {
        return new self(
            metric: $metric,
            start: $start->value,
            end: $end->value,
            difference: $end->value - $start->value,
            percentage: Percentage::change($start->value, $end->value),
            startRecordedAt: $start->recordedAt,
            endRecordedAt: $end->recordedAt,
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

    public function isFlat(): bool
    {
        return $this->difference == 0;
    }

    public function trend(): Trend
    {
        return Trend::fromDifference($this->difference);
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'start' => $this->start,
            'end' => $this->end,
            'difference' => $this->difference,
            'percentage' => $this->percentage,
            'start_recorded_at' => $this->startRecordedAt->toIso8601String(),
            'end_recorded_at' => $this->endRecordedAt->toIso8601String(),
            'trend' => $this->trend()->value,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
