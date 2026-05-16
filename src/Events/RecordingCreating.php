<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Events;

use BJTheCod3r\Recordables\Contracts\Recordable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched immediately before a recording is persisted.
 *
 * Listeners may mutate the public $data array directly, or call
 * $event->cancel() to abort the recording.
 */
class RecordingCreating
{
    use Dispatchable;

    public bool $cancelled = false;

    public function __construct(
        public Recordable $recordable,
        public array $data,
        public ?string $changeType = null,
        public ?string $changeSource = null,
        public ?Model $causer = null,
    ) {}

    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
