<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Concerns;

use BJTheCod3r\Recordables\Enums\ChangeType;
use BJTheCod3r\Recordables\Enums\Trend;
use BJTheCod3r\Recordables\Events\RecordingCreated;
use BJTheCod3r\Recordables\Events\RecordingCreating;
use BJTheCod3r\Recordables\Exceptions\InsufficientRecordingsException;
use BJTheCod3r\Recordables\Exceptions\InvalidRecordableMetricException;
use BJTheCod3r\Recordables\Exceptions\MissingRecordableDefinitionException;
use BJTheCod3r\Recordables\Exceptions\NonNumericMetricException;
use BJTheCod3r\Recordables\Jobs\CreateRecordingJob;
use BJTheCod3r\Recordables\Models\Recording;
use BJTheCod3r\Recordables\ValueObjects\ChartData;
use BJTheCod3r\Recordables\ValueObjects\ChartPoint;
use BJTheCod3r\Recordables\ValueObjects\Delta;
use BJTheCod3r\Recordables\ValueObjects\Growth;
use BJTheCod3r\Recordables\ValueObjects\History;
use BJTheCod3r\Recordables\ValueObjects\HistoryPoint;
use BJTheCod3r\Recordables\ValueObjects\MinMax;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Gives an Eloquent model the ability to persist snapshots of its data
 * over time into the `recordings` table.
 *
 * The model declares which attributes are recordable either via a
 * `$recordable` array property (the common case) or by overriding
 * {@see toRecording()} for computed snapshots.
 *
 * Auto-recording on `created` / `updated` model events is on by default;
 * each path is independently configurable globally
 * ({@see recordables.auto_record}) or per-model
 * (`$recordOnCreate` / `$recordOnUpdate` / `$recordOnlyOnChange`).
 *
 * Listeners may intercept persistence via the {@see RecordingCreating}
 * event — mutate `$event->data` or call `$event->cancel()`.
 */
trait HasRecordings
{
    protected bool $recordingsSilent = false;

    public static function bootHasRecordings(): void
    {
        static::created(function (Model $model): void {
            $model->handleAutoRecording('on_create', ChangeType::Created->value);
        });

        static::updated(function (Model $model): void {
            $model->handleAutoRecording('on_update', ChangeType::Updated->value);
        });
    }

    public function recordings(): MorphMany
    {
        $model = (string) config('recordables.recording_model', Recording::class);

        return $this->morphMany($model, 'recordable')->orderBy('recorded_at');
    }

    /**
     * Build the array of attributes that compose a snapshot.
     *
     * The default implementation returns `$this->only($recordable)`. Override
     * in the host model when the snapshot includes computed values.
     *
     * @return array<string, mixed>
     *
     * @throws MissingRecordableDefinitionException When neither `$recordable` is declared nor `toRecording()` overridden.
     */
    public function toRecording(): array
    {
        if (property_exists($this, 'recordable') && is_array($this->recordable)) {
            return $this->only($this->recordable);
        }

        throw MissingRecordableDefinitionException::for(static::class);
    }

    protected function handleAutoRecording(string $hook, string $changeType): void
    {
        if (! $this->autoRecordEnabled($hook)) {
            return;
        }

        if ($hook === 'on_update' && $this->recordOnlyOnChange() && ! $this->hasRecordingChanges()) {
            return;
        }

        $this->record(changeType: $changeType);
    }

    /**
     * Decide whether auto-recording is enabled for the given hook.
     *
     * All three of these must be true:
     *   - global config `recordables.auto_record.enabled`
     *   - hook-specific config `recordables.auto_record.{$hook}`
     *   - either no per-model `$recordOnCreate` / `$recordOnUpdate` property,
     *     or that property is truthy
     *
     * @param  string  $hook  Either `'on_create'` or `'on_update'`.
     */
    public function autoRecordEnabled(string $hook): bool
    {
        $property = $hook === 'on_create' ? 'recordOnCreate' : 'recordOnUpdate';

        return (bool) config('recordables.auto_record.enabled', true)
            && (bool) config("recordables.auto_record.$hook", true)
            && (! property_exists($this, $property) || $this->{$property});
    }

    public function recordOnlyOnChange(): bool
    {
        if (property_exists($this, 'recordOnlyOnChange')) {
            return $this->recordOnlyOnChange;
        }

        return (bool) config('recordables.auto_record.only_on_change', true);
    }

    /**
     * Whether any recordable attribute changed during the current save.
     *
     * Compares the keys of {@see toRecording()} against `getChanges()`. Updates
     * to non-recordable columns therefore do not trigger an auto-recording.
     */
    public function hasRecordingChanges(): bool
    {
        $recordableKeys = array_keys($this->toRecording());
        $changed = array_keys($this->getChanges());

        return ! empty(array_intersect($recordableKeys, $changed));
    }

    /**
     * Persist a snapshot of this model.
     *
     * Flow: builds `$data` from {@see toRecording()} if omitted, resolves the
     * causer from auth if not supplied, dispatches {@see RecordingCreating}
     * (which listeners may mutate or cancel), then writes the row and
     * dispatches {@see RecordingCreated}.
     *
     * Returns `null` if a listener called `$event->cancel()`; otherwise the
     * persisted Recording.
     *
     * @param  array<string, mixed>|null  $data          Snapshot data; defaults to `toRecording()`.
     * @param  string|null                $changeType    e.g. `created`, `updated`, `synced`, `imported`.
     * @param  string|null                $changeSource  Free-form origin tag (e.g. `cms_sync`, `scheduler`).
     * @param  Model|null                 $causer        Actor that triggered this; auto-resolved from auth when null.
     * @param  CarbonInterface|null       $recordedAt    Logical timestamp; defaults to `now()`. Use for backfills.
     */
    public function record(
        ?array $data = null,
        ?string $changeType = null,
        ?string $changeSource = null,
        ?Model $causer = null,
        ?CarbonInterface $recordedAt = null,
    ): ?Recording {
        $data ??= $this->toRecording();
        $causer ??= $this->resolveCauser();

        if (! $this->recordingsSilent) {
            $event = new RecordingCreating($this, $data, $changeType, $changeSource, $causer);
            event($event);

            if ($event->cancelled) {
                return null;
            }

            $data = $event->data;
            $changeType = $event->changeType;
            $changeSource = $event->changeSource;
            $causer = $event->causer;
        }

        /** @var Recording $recording */
        $recording = $this->recordings()->create([
            'data' => $data,
            'change_type' => $changeType,
            'change_source' => $changeSource,
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'recorded_at' => $recordedAt ?? now(),
        ]);

        if (! $this->recordingsSilent) {
            RecordingCreated::dispatch($recording);
        }

        return $recording;
    }

    /**
     * Record only when the current snapshot differs from the latest persisted one.
     *
     * Comparison is against the most recently stored Recording (not the model's
     * own dirty state), so this is safe to call after manual mutations or
     * imports without producing duplicates.
     *
     * Returns `null` when nothing changed.
     */
    public function recordIfChanged(
        ?string $changeType = null,
        ?string $changeSource = null,
        ?Model $causer = null,
    ): ?Recording {
        $latest = $this->latestRecording();
        $current = $this->toRecording();

        if ($latest && $latest->data == $this->asStored($current)) {
            return null;
        }

        return $this->record($current, $changeType, $changeSource, $causer);
    }

    /**
     * The snapshot as it reads back from the `data` column.
     *
     * `toRecording()` returns cast values (backed enums, dates, value
     * objects) while a stored recording holds their JSON form, and an enum
     * never loosely equals its string value. Round-tripping through JSON
     * puts both sides in the same shape before comparing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function asStored(array $data): array
    {
        return (array) json_decode((string) json_encode($data), true);
    }

    /**
     * Record without dispatching `RecordingCreating` or `RecordingCreated`.
     *
     * Useful inside seeders, imports, or any path where you've already
     * orchestrated the surrounding logic and don't want listeners to run.
     */
    public function recordSilently(
        ?array $data = null,
        ?string $changeType = null,
        ?string $changeSource = null,
        ?Model $causer = null,
        ?CarbonInterface $recordedAt = null,
    ): ?Recording {
        $this->recordingsSilent = true;

        try {
            return $this->record($data, $changeType, $changeSource, $causer, $recordedAt);
        } finally {
            $this->recordingsSilent = false;
        }
    }

    /**
     * Dispatch a queued job that calls {@see record()} on this model.
     *
     * Useful for high-write models or expensive `toRecording()` snapshots
     * (e.g. ones that enrich via an external API). The job is dispatched on
     * the connection/queue configured under `recordables.queue`.
     */
    public function recordOnQueue(
        ?array $data = null,
        ?string $changeType = null,
        ?string $changeSource = null,
        ?Model $causer = null,
        ?CarbonInterface $recordedAt = null,
    ): void {
        $connection = config('recordables.queue.connection');
        $queue = config('recordables.queue.queue', 'default');

        CreateRecordingJob::dispatch(
            static::class,
            $this->getKey(),
            $data ?? $this->toRecording(),
            $changeType,
            $changeSource,
            $causer?->getMorphClass(),
            $causer?->getKey(),
            $recordedAt?->toDateTimeString(),
        )
            ->onConnection($connection === null ? null : (string) $connection)
            ->onQueue($queue === null ? null : (string) $queue);
    }

    /**
     * Resolve the authenticated user (or any Eloquent model) as the causer.
     *
     * Returns `null` when causer resolution is disabled or no user is
     * authenticated on the configured guard. Non-Eloquent authenticatables
     * (rare) are coerced to `null` because the causer column is morphed.
     */
    protected function resolveCauser(): ?Model
    {
        if (! config('recordables.causer.enabled', true)) {
            return null;
        }

        $guard = config('recordables.causer.guard');
        $user = Auth::guard($guard === null ? null : (string) $guard)->user();

        return $user instanceof Model ? $user : null;
    }

    public function latestRecording(): ?Recording
    {
        return $this->recordings()->reorder('recorded_at', 'desc')->first();
    }

    public function firstRecording(): ?Recording
    {
        return $this->recordings()->reorder('recorded_at')->first();
    }

    /**
     * The most recent recording at or before the given moment.
     *
     * Boundary is inclusive — if a recording's `recorded_at` exactly equals
     * `$when`, it is returned. Useful for "what did this look like a week
     * ago?" lookups.
     */
    public function recordingAt(CarbonInterface $when): ?Recording
    {
        /** @var Recording|null */
        return $this->recordings()
            ->where('recorded_at', '<=', $when)
            ->reorder('recorded_at', 'desc')
            ->first();
    }

    /**
     * @return EloquentCollection<int, Recording>
     */
    public function recordingsBetween(CarbonInterface|string $start, CarbonInterface|string $end): EloquentCollection
    {
        return $this->recordings()
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Latest recording per period bucket.
     *
     * Groups all recordings by the given period (`hour`/`day`/`week`/
     * `month`/`year`) and keeps only the last recording of each bucket.
     * Useful for sparse charts where each x-axis tick should reflect the
     * most recent value within the bucket.
     *
     * @return Collection<int, Recording>
     */
    public function recordingsForPeriod(string $period): Collection
    {
        $format = $this->periodToFormat($period);

        return $this->recordings()
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn (Recording $r): string => $r->recorded_at->format($format))
            ->map(function (EloquentCollection $bucket): ?Recording {
                /** @var Recording|null */
                return $bucket->last();
            })
            ->values();
    }

    /**
     * The time series of a single metric across all recordings.
     *
     * Recordings missing the metric (or storing it as `null`) are silently
     * skipped, so adding a new recordable field doesn't invalidate older
     * snapshots, and missing samples in integrations don't break analytics.
     *
     * @throws InvalidRecordableMetricException When no recording contains the metric.
     * @throws NonNumericMetricException        When a recording stores the metric as a non-numeric value.
     */
    public function recordingHistory(string $metric): History
    {
        $points = [];

        foreach ($this->recordings()->orderBy('recorded_at')->get() as $recording) {
            $value = ($recording->data ?? [])[$metric] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_int($value) && ! is_float($value)) {
                throw NonNumericMetricException::for($metric, static::class, $value);
            }

            $points[] = new HistoryPoint(
                value: $value,
                recordedAt: CarbonImmutable::instance($recording->recorded_at),
            );
        }

        if (empty($points)) {
            throw InvalidRecordableMetricException::for($metric, static::class);
        }

        return new History($metric, $points);
    }

    /**
     * Growth in a metric over time.
     *
     * Without `$period`: compares the earliest and latest recording of the
     * metric across the model's entire history.
     *
     * With `$period`: compares the latest recording in the current calendar
     * period against the latest in the previous one — e.g. "this month vs
     * last month". Calendar-aligned via Carbon, so `'week'` means ISO weeks
     * (not rolling 7-day windows). Supported periods: `hour`, `day`,
     * `week`, `month`, `year`.
     *
     * @throws InvalidArgumentException         When `$period` is provided but not recognised.
     * @throws InvalidRecordableMetricException When the metric has no recordings (all-time mode).
     * @throws InsufficientRecordingsException  When fewer than two comparable points exist.
     *
     * @see recordingGrowthOrNull() For a non-throwing variant.
     */
    public function recordingGrowth(string $metric, ?string $period = null): Growth
    {
        if ($period !== null) {
            [$currentStart, $previousStart] = $this->periodBoundaries($period);

            $current = $this->latestMetricPointBetween($metric, $currentStart, null);
            $previous = $this->latestMetricPointBetween($metric, $previousStart, $currentStart);

            if ($current === null || $previous === null) {
                $found = ($current !== null ? 1 : 0) + ($previous !== null ? 1 : 0);
                throw InsufficientRecordingsException::for($metric, 2, $found);
            }

            return Growth::between($metric, $previous, $current);
        }

        $history = $this->recordingHistory($metric);

        if ($history->count() < 2) {
            throw InsufficientRecordingsException::for($metric, 2, $history->count());
        }

        return Growth::between($metric, $history->first(), $history->last());
    }

    public function recordingGrowthOrNull(string $metric, ?string $period = null): ?Growth
    {
        try {
            return $this->recordingGrowth($metric, $period);
        } catch (InsufficientRecordingsException|InvalidRecordableMetricException) {
            return null;
        }
    }

    /**
     * Start of the current calendar period and start of the previous one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws InvalidArgumentException When the period is not recognised.
     */
    protected function periodBoundaries(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'hour'  => [$now->startOfHour(),  $now->subHour()->startOfHour()],
            'day'   => [$now->startOfDay(),   $now->subDay()->startOfDay()],
            'week'  => [$now->startOfWeek(),  $now->subWeek()->startOfWeek()],
            'month' => [$now->startOfMonth(), $now->subMonth()->startOfMonth()],
            'year'  => [$now->startOfYear(),  $now->subYear()->startOfYear()],
            default => throw new InvalidArgumentException("Unsupported period: {$period}"),
        };
    }

    /**
     * Latest recording inside the given window that carries the metric key.
     *
     * `$before` is exclusive (so boundary recordings count as part of the
     * later window, matching how `startOfWeek` etc. behave). `$before: null`
     * means "up to now". Recordings missing the metric (or storing it as
     * `null`) are skipped.
     *
     * @throws NonNumericMetricException When a recording stores the metric as a non-numeric value.
     */
    protected function latestMetricPointBetween(
        string $metric,
        CarbonInterface $after,
        ?CarbonInterface $before,
    ): ?HistoryPoint {
        $query = $this->recordings()
            ->reorder('recorded_at', 'desc')
            ->where('recorded_at', '>=', $after);

        if ($before !== null) {
            $query->where('recorded_at', '<', $before);
        }

        foreach ($query->get() as $recording) {
            $value = ($recording->data ?? [])[$metric] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_int($value) && ! is_float($value)) {
                throw NonNumericMetricException::for($metric, static::class, $value);
            }

            return new HistoryPoint(
                value: $value,
                recordedAt: CarbonImmutable::instance($recording->recorded_at),
            );
        }

        return null;
    }

    /**
     * Difference between the latest two recordings of a metric.
     *
     * Returns `null` when the metric does not exist on any recording or
     * fewer than two recordings are present.
     */
    public function recordingDelta(string $metric): ?Delta
    {
        try {
            $history = $this->recordingHistory($metric);
        } catch (InvalidRecordableMetricException) {
            $history = null;
        }

        if ($history === null || $history->count() < 2) {
            return null;
        }

        $points = $history->all();
        $count = count($points);

        return Delta::between($metric, $points[$count - 2]->value, $points[$count - 1]->value);
    }

    public function recordingAverage(string $metric): float
    {
        $values = $this->recordingHistory($metric)->values();

        return array_sum($values) / count($values);
    }

    /**
     * Min/max of a metric across all recordings, plus the timestamps where
     * each extremum was recorded.
     *
     * Returns `null` when the metric does not exist on any recording.
     */
    public function recordingMinMax(string $metric): ?MinMax
    {
        try {
            $history = $this->recordingHistory($metric);
        } catch (InvalidRecordableMetricException) {
            return null;
        }

        $minPoint = $history->first();
        $maxPoint = $history->first();

        foreach ($history as $point) {
            if ($point->value < $minPoint->value) {
                $minPoint = $point;
            }
            if ($point->value > $maxPoint->value) {
                $maxPoint = $point;
            }
        }

        return new MinMax(
            metric: $metric,
            min: $minPoint->value,
            max: $maxPoint->value,
            minRecordedAt: $minPoint->recordedAt,
            maxRecordedAt: $maxPoint->recordedAt,
        );
    }

    /**
     * Direction of the most recent change in a metric.
     *
     * Returns `null` when there isn't enough data to determine a trend
     * (metric absent, or fewer than two recordings). Callers rendering an
     * up/down/flat indicator should treat `null` as "no data" — not "flat".
     */
    public function recordingTrend(string $metric): ?Trend
    {
        $delta = $this->recordingDelta($metric);

        return $delta === null
            ? null
            : Trend::fromDifference($delta->difference);
    }

    /**
     * Chart-friendly series for a metric, bucketed by period.
     *
     * When multiple recordings fall in the same bucket (e.g. several writes
     * on the same day for `period: 'day'`), the latest in the bucket wins.
     *
     * @param  string  $period  One of `hour`, `day`, `week`, `month`, `year`.
     */
    public function recordingChartData(string $metric, string $period = 'day'): ChartData
    {
        $history = $this->recordingHistory($metric);
        $format = $this->periodToFormat($period);

        $buckets = [];
        foreach ($history as $point) {
            $key = $point->recordedAt->format($format);
            $buckets[$key] = $point;
        }

        $points = array_map(
            fn (HistoryPoint $p) => new ChartPoint(x: $p->recordedAt, y: $p->value),
            array_values($buckets),
        );

        return new ChartData(metric: $metric, period: $period, points: $points);
    }

    protected function periodToFormat(string $period): string
    {
        return match ($period) {
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'o-W',
            'month' => 'Y-m',
            'year' => 'Y',
            default => 'Y-m-d',
        };
    }

    /**
     * Delete recordings for this model by age and/or by retention count.
     *
     * Either argument is optional; when both are `null`, falls back to the
     * model's `$keepRecordingsForDays` / `$keepRecordingsCount` properties.
     * Returns the total number of rows deleted across both strategies.
     */
    public function pruneRecordings(?int $keepDays = null, ?int $keepCount = null): int
    {
        $keepDays ??= property_exists($this, 'keepRecordingsForDays') ? $this->keepRecordingsForDays : null;
        $keepCount ??= property_exists($this, 'keepRecordingsCount') ? $this->keepRecordingsCount : null;

        $deleted = 0;

        if ($keepDays !== null) {
            $deleted += $this->recordings()
                ->where('recorded_at', '<', now()->subDays($keepDays))
                ->delete();
        }

        if ($keepCount !== null) {
            $idsToKeep = $this->recordings()
                ->reorder('recorded_at', 'desc')
                ->limit($keepCount)
                ->pluck('id');

            $deleted += $this->recordings()
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }

        return $deleted;
    }
}
