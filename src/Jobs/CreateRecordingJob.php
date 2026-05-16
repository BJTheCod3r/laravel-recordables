<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Jobs;

use BJTheCod3r\Recordables\Contracts\Recordable;
use BJTheCod3r\Recordables\Exceptions\RecordingFailedException;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $recordableClass,
        public int|string $recordableId,
        public array $data,
        public ?string $changeType = null,
        public ?string $changeSource = null,
        public ?string $causerType = null,
        public int|string|null $causerId = null,
        public ?string $recordedAt = null,
    ) {}

    public function handle(): void
    {
        /** @var Recordable|Model|null $recordable */
        $recordable = $this->recordableClass::find($this->recordableId);

        if ($recordable === null) {
            return;
        }

        $causer = null;
        if ($this->causerType && $this->causerId !== null) {
            $causer = $this->causerType::find($this->causerId);
        }

        try {
            $recordable->record(
                data: $this->data,
                changeType: $this->changeType,
                changeSource: $this->changeSource,
                causer: $causer,
                recordedAt: $this->recordedAt ? Carbon::parse($this->recordedAt) : null,
            );
        } catch (Throwable $e) {
            throw RecordingFailedException::wrap($e);
        }
    }
}
