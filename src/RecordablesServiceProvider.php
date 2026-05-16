<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables;

use BJTheCod3r\Recordables\Commands\PruneRecordingsCommand;
use Illuminate\Support\ServiceProvider;

class RecordablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recordables.php', 'recordables');

        $this->app->singleton(RecordablesManager::class, function ($app) {
            return new RecordablesManager($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/recordables.php' => config_path('recordables.php'),
            ], 'recordables-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_recordings_table.php.stub' => database_path(
                    'migrations/'.date('Y_m_d_His').'_create_recordings_table.php'
                ),
            ], 'recordables-migrations');

            $this->commands([
                PruneRecordingsCommand::class,
            ]);
        }
    }
}
