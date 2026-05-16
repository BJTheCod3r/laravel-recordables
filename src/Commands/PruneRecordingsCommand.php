<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Commands;

use BJTheCod3r\Recordables\Models\Recording;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Prune recordings according to age (--days) and/or per-recordable retention (--keep).
 *
 * Strategies combine: passing both flags applies age-based pruning first, then
 * caps remaining rows per recordable. Without --model, every distinct
 * recordable_type in the table is processed.
 */
class PruneRecordingsCommand extends Command
{
    protected $signature = 'recordables:prune
        {--days= : Delete recordings older than this many days}
        {--keep= : Keep only the latest N recordings per recordable}
        {--model= : Only prune recordings for the given recordable class}
        {--dry-run : Report what would be pruned without deleting}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Prune old recordings based on configured retention.';

    /**
     * Execute the command.
     *
     * Exit codes:
     *  - {@see self::SUCCESS} pruning ran (or had nothing to do)
     *  - {@see self::FAILURE} no retention strategy was provided via flags or config
     *  - {@see self::INVALID} user aborted the destructive confirmation
     */
    public function handle(): int
    {
        $days = $this->resolveIntOption('days', 'recordables.pruning.keep_days');
        $keep = $this->resolveIntOption('keep', 'recordables.pruning.keep_count');

        if ($days === null && $keep === null) {
            $this->error('Provide --days, --keep, or configure recordables.pruning.');
            return self::FAILURE;
        }

        $recordingModel = config('recordables.recording_model', Recording::class);
        $types = $this->resolveTypes($recordingModel, $this->option('model'));

        if (empty($types)) {
            $this->info('No recordings to prune.');
            $exit = self::SUCCESS;
        } elseif (! $this->confirmDestructive()) {
            $this->warn('Aborted.');
            $exit = self::INVALID;
        } else {
            $this->executePrune($recordingModel, $types, $days, $keep);
            $exit = self::SUCCESS;
        }

        return $exit;
    }

    /**
     * Resolve a numeric command option, falling back to a config key.
     *
     * @param  string  $option     Command option name (without `--`).
     * @param  string  $configKey  Dotted config path consulted when the option is absent.
     * @return int|null  Null when neither source provides a value.
     */
    protected function resolveIntOption(string $option, string $configKey): ?int
    {
        $value = $this->option($option) ?? config($configKey);

        return $value === null ? null : (int) $value;
    }

    /**
     * Resolve the set of recordable_type values to operate on.
     *
     * When a model class is provided, it is validated and reduced to its
     * morph alias; otherwise the table is queried for every distinct type.
     *
     * @param  class-string                     $recordingModel  FQCN of the configured Recording model.
     * @param  class-string|null                $modelClass      Optional concrete recordable class to scope to.
     * @return array<int, string>
     *
     * @throws InvalidArgumentException  When the supplied model class does not exist.
     */
    protected function resolveTypes(string $recordingModel, ?string $modelClass): array
    {
        if ($modelClass !== null && ! class_exists($modelClass)) {
            throw new InvalidArgumentException("Model class [{$modelClass}] does not exist.");
        }

        return $modelClass !== null
            ? [(new $modelClass)->getMorphClass()]
            : $recordingModel::query()
                ->select('recordable_type')
                ->distinct()
                ->pluck('recordable_type')
                ->all();
    }

    /**
     * Determine whether the destructive operation may proceed.
     *
     * Auto-confirms under --force, --dry-run, or in non-interactive shells
     * (e.g. cron, CI); otherwise prompts the operator and defaults to "no".
     */
    protected function confirmDestructive(): bool
    {
        return (bool) $this->option('force')
            || (bool) $this->option('dry-run')
            || ! $this->input->isInteractive()
            || $this->confirm('This will permanently delete recordings. Continue?');
    }

    /**
     * Run the pruning loop over each recordable type and report totals.
     *
     * @param  class-string         $recordingModel
     * @param  array<int, string>   $types
     */
    protected function executePrune(string $recordingModel, array $types, ?int $days, ?int $keep): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalAffected = 0;

        foreach ($types as $morphClass) {
            $affected = $this->pruneFor($recordingModel, $morphClass, $days, $keep, $dryRun);
            $this->line(sprintf('  <comment>%s</comment>: %s', $morphClass, number_format($affected)));
            $totalAffected += $affected;
        }

        $verb = $dryRun ? 'Would prune' : 'Pruned';
        $this->info("{$verb} ".number_format($totalAffected).' recording(s).');
    }

    /**
     * Apply both pruning strategies, if configured, for a single morph type.
     *
     * @param  class-string  $recordingModel
     * @return int  Number of rows affected (or that would be affected on --dry-run).
     */
    protected function pruneFor(
        string $recordingModel,
        string $morphClass,
        ?int $days,
        ?int $keep,
        bool $dryRun,
    ): int {
        $affected = 0;

        if ($days !== null) {
            $affected += $this->pruneOlderThan($recordingModel, $morphClass, $days, $dryRun);
        }

        if ($keep !== null) {
            $affected += $this->pruneKeepingLatest($recordingModel, $morphClass, $keep, $dryRun);
        }

        return $affected;
    }

    /**
     * Delete recordings older than the given number of days.
     *
     * @param  class-string  $recordingModel
     * @return int  Rows deleted (or counted in --dry-run mode).
     */
    protected function pruneOlderThan(string $recordingModel, string $morphClass, int $days, bool $dryRun): int
    {
        $query = $recordingModel::query()
            ->where('recordable_type', $morphClass)
            ->where('recorded_at', '<', now()->subDays($days));

        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * For every recordable of the given morph type, keep only its latest N rows.
     *
     * Issues two queries per recordable (select-keep / delete-rest) rather
     * than a single subquery, to remain compatible with databases that do
     * not allow deleting from a table referenced by its own subquery.
     *
     * @param  class-string  $recordingModel
     * @return int  Rows deleted (or counted in --dry-run mode).
     */
    protected function pruneKeepingLatest(string $recordingModel, string $morphClass, int $keep, bool $dryRun): int
    {
        $affected = 0;

        $recordableIds = $recordingModel::query()
            ->select('recordable_id')
            ->where('recordable_type', $morphClass)
            ->distinct()
            ->pluck('recordable_id');

        foreach ($recordableIds as $id) {
            $idsToKeep = $recordingModel::query()
                ->where('recordable_type', $morphClass)
                ->where('recordable_id', $id)
                ->orderByDesc('recorded_at')
                ->limit($keep)
                ->pluck('id');

            $query = $recordingModel::query()
                ->where('recordable_type', $morphClass)
                ->where('recordable_id', $id)
                ->whereNotIn('id', $idsToKeep);

            $affected += $dryRun ? $query->count() : $query->delete();
        }

        return $affected;
    }
}
