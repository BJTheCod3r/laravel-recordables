<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recordings Table
    |--------------------------------------------------------------------------
    */

    'table_name' => 'recordings',

    /*
    |--------------------------------------------------------------------------
    | Recording Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist recordings. Extend it to add custom
    | columns or behavior, then point this binding at your class.
    |
    */

    'recording_model' => BJTheCod3r\Recordables\Models\Recording::class,

    /*
    |--------------------------------------------------------------------------
    | Primary Key
    |--------------------------------------------------------------------------
    |
    | Either "id" (auto-incrementing bigint) or "ulid". ULIDs are recommended
    | for high-write workloads since they are time-ordered.
    |
    */

    'primary_key' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Automatic Recording
    |--------------------------------------------------------------------------
    */

    'auto_record' => [
        'enabled' => true,
        'on_create' => true,
        'on_update' => true,
        'only_on_change' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Causer Resolution
    |--------------------------------------------------------------------------
    */

    'causer' => [
        'enabled' => true,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    */

    'pruning' => [
        'enabled' => false,
        'keep_days' => 365,
        'keep_count' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'enabled' => false,
        'connection' => null,
        'queue' => 'default',
    ],

];
