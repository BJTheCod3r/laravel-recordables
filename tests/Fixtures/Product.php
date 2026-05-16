<?php

declare(strict_types=1);

namespace BJTheCod3r\Recordables\Tests\Fixtures;

use BJTheCod3r\Recordables\Concerns\HasRecordings;
use BJTheCod3r\Recordables\Contracts\Recordable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Recordable
{
    use HasRecordings;

    protected $guarded = [];

    protected array $recordable = [
        'price',
        'stock',
        'views',
    ];

    protected $casts = [
        'price' => 'int',
        'stock' => 'int',
        'views' => 'int',
    ];
}
