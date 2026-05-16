<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Testing;

use BJTheCod3r\Recordables\Contracts\Recordable;
use BJTheCod3r\Recordables\Events\RecordingCreating;
use BJTheCod3r\Recordables\RecordablesManager;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert as PHPUnit;

class RecordablesFake extends RecordablesManager
{
    /** @var array<int, array{recordable: Recordable, data: array, changeType: ?string, changeSource: ?string}> */
    protected array $recorded = [];

    public function __construct()
    {
        parent::__construct(Container::getInstance());

        Event::listen(RecordingCreating::class, function (RecordingCreating $event): void {
            $this->recorded[] = [
                'recordable' => $event->recordable,
                'data' => $event->data,
                'changeType' => $event->changeType,
                'changeSource' => $event->changeSource,
            ];

            $event->cancel();
        });
    }

    public function assertRecorded(Recordable $recordable, ?callable $callback = null): void
    {
        $matches = $this->matchesFor($recordable);

        if ($callback !== null) {
            $matches = array_filter($matches, fn (array $entry) => $callback($entry['data'], $entry));
        }

        PHPUnit::assertNotEmpty(
            $matches,
            sprintf('Expected a recording for %s, none found.', $recordable::class),
        );
    }

    public function assertRecordedTimes(Recordable $recordable, int $times): void
    {
        $count = count($this->matchesFor($recordable));

        PHPUnit::assertSame(
            $times,
            $count,
            sprintf('Expected %d recordings for %s, got %d.', $times, $recordable::class, $count),
        );
    }

    public function assertRecordedWith(Recordable $recordable, callable $callback): void
    {
        $this->assertRecorded($recordable, $callback);
    }

    public function assertNotRecorded(Recordable $recordable): void
    {
        PHPUnit::assertEmpty(
            $this->matchesFor($recordable),
            sprintf('Expected no recordings for %s, found some.', $recordable::class),
        );
    }

    public function assertNothingRecorded(): void
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            sprintf('Expected nothing recorded, found %d.', count($this->recorded)),
        );
    }

    /** @return array<int, array{recordable: Recordable, data: array, changeType: ?string, changeSource: ?string}> */
    protected function matchesFor(Recordable $recordable): array
    {
        return array_values(array_filter(
            $this->recorded,
            fn (array $entry) => $entry['recordable']::class === $recordable::class
                && $entry['recordable']->getKey() === $recordable->getKey(),
        ));
    }
}
