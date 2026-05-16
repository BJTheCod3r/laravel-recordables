<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables;

use BJTheCod3r\Recordables\Contracts\Recordable;
use BJTheCod3r\Recordables\Models\Recording;
use BJTheCod3r\Recordables\Testing\RecordablesFake;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Facade;

class RecordablesManager
{
    public function __construct(protected Container $container) {}

    public function recordingModel(): string
    {
        return (string) config('recordables.recording_model', Recording::class);
    }

    public function newRecording(): Recording
    {
        $class = $this->recordingModel();

        return new $class;
    }

    public function fake(): RecordablesFake
    {
        $fake = new RecordablesFake;

        $this->container->instance(self::class, $fake);

        Facade::clearResolvedInstance(self::class);

        return $fake;
    }
}
