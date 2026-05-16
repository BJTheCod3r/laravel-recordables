<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A persisted snapshot of a recordable model's data at a point in time.
 *
 * @property int|string                         $id
 * @property string                             $recordable_type
 * @property int|string                         $recordable_id
 * @property array<string, mixed>               $data
 * @property string|null                        $change_type
 * @property string|null                        $change_source
 * @property string|null                        $causer_type
 * @property int|string|null                    $causer_id
 * @property Carbon         $recorded_at
 * @property Carbon         $created_at
 * @property Carbon         $updated_at
 */
class Recording extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Switch the model to ULID mode when configured, so the framework
     * generates a 26-character ULID on insert instead of an auto-increment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (config('recordables.primary_key', 'id') === 'ulid') {
            $this->usesUniqueIds = true;
            $this->incrementing = false;
            $this->keyType = 'string';
        }
    }

    /**
     * Resolve the table name from the recordables config when not explicitly set.
     */
    public function getTable(): string
    {
        return (string) ($this->table ?? config('recordables.table_name', 'recordings'));
    }

    /**
     * Columns Laravel should generate unique IDs for.
     *
     * Returns the primary key column when ULID mode is on, an empty array
     * otherwise. Part of the `HasUlids`-style contract Laravel checks during
     * model creation.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return config('recordables.primary_key', 'id') === 'ulid'
            ? [$this->getKeyName()]
            : [];
    }

    /**
     * Generate the next ULID value. Only invoked when ULID mode is active.
     */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    /**
     * The model that owns this snapshot (e.g., Product, Campaign, Article).
     */
    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The actor (user, system, integration) that caused this snapshot, if known.
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to recordings of a given recordable Eloquent class.
     *
     * Resolves the morph alias so it works whether the class is in the
     * application's morph map.
     *
     * @param  class-string<Model>  $class
     */
    public function scopeForType(Builder $query, string $class): Builder
    {
        return $query->where('recordable_type', (new $class)->getMorphClass());
    }

    public function scopeOfChangeType(Builder $query, string $type): Builder
    {
        return $query->where('change_type', $type);
    }

    public function scopeOfChangeSource(Builder $query, string $source): Builder
    {
        return $query->where('change_source', $source);
    }

    public function scopeByCauser(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    /**
     * Scope to recordings whose recorded_at falls within an inclusive range.
     */
    public function scopeRecordedBetween(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end): Builder
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Filter by a value inside the `data` JSON column using the driver-native operator.
     *
     * Postgres uses the `->>` text-extraction operator; MySQL/SQLite use
     * `JSON_UNQUOTE(JSON_EXTRACT(...))`. Both yield a comparable scalar.
     *
     * `$key` and `$operator` are interpolated into raw SQL, so both are
     * validated against an allowlist before use — pass user input safely.
     *
     * Example: `Recording::query()->whereDataKey('price', '>', 10000)`.
     *
     * @throws InvalidArgumentException When `$key` is not a valid identifier or `$operator` is not in the allowlist.
     */
    public function scopeWhereDataKey(Builder $query, string $key, string $operator, mixed $value): Builder
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException(
                "Invalid JSON key [{$key}]; must match [A-Za-z_][A-Za-z0-9_]*."
            );
        }

        $normalisedOperator = strtolower($operator);
        $allowedOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'];

        if (! in_array($normalisedOperator, $allowedOperators, true)) {
            throw new InvalidArgumentException(
                "Invalid operator [{$operator}]; allowed: ".implode(', ', $allowedOperators).'.'
            );
        }

        $driver = $query->getConnection()->getDriverName();

        $expression = match ($driver) {
            'pgsql' => "data->>'{$key}'",
            'sqlite' => "json_extract(data, '$.{$key}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$key}'))",
        };

        return $query->whereRaw("$expression {$normalisedOperator} ?", [$value]);
    }
}
