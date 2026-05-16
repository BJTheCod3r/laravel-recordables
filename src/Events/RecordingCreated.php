<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Events;

use BJTheCod3r\Recordables\Models\Recording;
use Illuminate\Foundation\Events\Dispatchable;

class RecordingCreated
{
    use Dispatchable;

    public function __construct(
        public Recording $recording,
    ) {}
}
