<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\ValueObjects;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class HistoryPoint implements Arrayable, JsonSerializable
{
    public function __construct(
        public int|float $value,
        public CarbonImmutable $recordedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'recorded_at' => $this->recordedAt->toIso8601String(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
