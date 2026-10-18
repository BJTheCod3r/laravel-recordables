<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Tests;

use BJTheCod3r\Recordables\RecordablesServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RecordablesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price')->default(0);
            $table->integer('stock')->default(0);
            $table->integer('views')->default(0);
            $table->integer('non_recordable_column')->default(0);
            $table->string('status')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->morphs('recordable');
            $table->json('data');
            $table->string('change_type')->nullable();
            $table->string('change_source')->nullable();
            $table->nullableMorphs('causer');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            $table->index(
                ['recordable_type', 'recordable_id', 'recorded_at'],
                'recordings_recordable_recorded_at_index'
            );
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
