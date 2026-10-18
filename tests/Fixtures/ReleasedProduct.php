<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Tests\Fixtures;

use BJTheCod3r\Recordables\Concerns\HasRecordings;
use BJTheCod3r\Recordables\Contracts\Recordable;
use Illuminate\Database\Eloquent\Model;

/**
 * A product whose recorded attributes are cast to objects: a backed enum
 * and a date.
 */
class ReleasedProduct extends Model implements Recordable
{
    use HasRecordings;

    protected $table = 'products';

    protected $guarded = [];

    protected array $recordable = [
        'status',
        'released_at',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'released_at' => 'datetime',
    ];
}
