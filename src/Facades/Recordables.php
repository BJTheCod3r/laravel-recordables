<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Facades;

use BJTheCod3r\Recordables\Contracts\Recordable;
use BJTheCod3r\Recordables\Models\Recording;
use BJTheCod3r\Recordables\RecordablesManager;
use BJTheCod3r\Recordables\Testing\RecordablesFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Recording newRecording()
 * @method static string recordingModel()
 * @method static RecordablesFake fake()
 * @method static void assertRecorded(Recordable $recordable, ?callable $callback = null)
 * @method static void assertRecordedTimes(Recordable $recordable, int $times)
 * @method static void assertRecordedWith(Recordable $recordable, callable $callback)
 * @method static void assertNotRecorded(Recordable $recordable)
 * @method static void assertNothingRecorded()
 */
class Recordables extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RecordablesManager::class;
    }
}
