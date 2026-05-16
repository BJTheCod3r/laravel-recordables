<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Support;

final class Percentage
{
    /**
     * Percent change from `$from` to `$to`, rounded to two decimal places.
     *
     * Handles the divide-by-zero edges:
     *   - Both `$from` and `$to` are zero → `0.0` (no change).
     *   - Only `$from` is zero, `$to` non-zero → `100.0` (signal a movement
     *     without raising; mathematically undefined, but conventionally
     *     reported as "100% growth" for analytics).
     */
    public static function change(int|float $from, int|float $to): float
    {
        if ($from == 0) {
            return $to == 0 ? 0.0 : 100.0;
        }

        return round((($to - $from) / $from) * 100, 2);
    }
}
