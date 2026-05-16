<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Enums;

enum Trend: string
{
    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';

    public static function fromDifference(int|float $difference): self
    {
        return match (true) {
            $difference > 0 => self::Up,
            $difference < 0 => self::Down,
            default => self::Flat,
        };
    }
}
