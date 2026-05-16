<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Enums\Trend;
use BJTheCod3r\Recordables\Exceptions\InsufficientRecordingsException;
use BJTheCod3r\Recordables\Exceptions\InvalidRecordableMetricException;
use BJTheCod3r\Recordables\Exceptions\NonNumericMetricException;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;
use BJTheCod3r\Recordables\ValueObjects\ChartData;
use BJTheCod3r\Recordables\ValueObjects\Delta;
use BJTheCod3r\Recordables\ValueObjects\Growth;
use BJTheCod3r\Recordables\ValueObjects\History;
use BJTheCod3r\Recordables\ValueObjects\MinMax;

beforeEach(function () {
    config()->set('recordables.auto_record.enabled', false);

    $this->product = Product::create(['name' => 'Widget', 'price' => 100]);

    foreach ([1000, 1200, 1500] as $i => $value) {
        $this->product->record(
            data: ['price' => $value],
            recordedAt: now()->subDays(3 - $i),
        );
    }
});

it('returns recording history as a value object', function () {
    $history = $this->product->recordingHistory('price');

    expect($history)->toBeInstanceOf(History::class);
    expect($history->count())->toBe(3);
    expect($history->values())->toBe([1000, 1200, 1500]);
});

it('throws when asking for an unknown metric', function () {
    $this->product->recordingHistory('unknown');
})->throws(InvalidRecordableMetricException::class);

it('calculates growth between earliest and latest recording', function () {
    $growth = $this->product->recordingGrowth('price');

    expect($growth)->toBeInstanceOf(Growth::class);
    expect($growth->start)->toBe(1000);
    expect($growth->end)->toBe(1500);
    expect($growth->difference)->toBe(500);
    expect($growth->percentage)->toBe(50.0);
    expect($growth->trend())->toBe(Trend::Up);
    expect($growth->isPositive())->toBeTrue();
});

it('throws when there are not enough recordings for growth', function () {
    $solo = Product::create(['name' => 'Solo Widget']);
    $solo->record(data: ['price' => 5]);
    $solo->recordingGrowth('price');
})->throws(InsufficientRecordingsException::class);

it('returns null growth when there are not enough recordings', function () {
    $solo = Product::create(['name' => 'Solo Widget']);
    $solo->record(data: ['price' => 5]);

    expect($solo->recordingGrowthOrNull('price'))->toBeNull();
});

it('returns delta between last two recordings', function () {
    $delta = $this->product->recordingDelta('price');

    expect($delta)->toBeInstanceOf(Delta::class);
    expect($delta->previous)->toBe(1200);
    expect($delta->current)->toBe(1500);
    expect($delta->difference)->toBe(300);
});

it('returns min/max as a value object with timestamps', function () {
    $minmax = $this->product->recordingMinMax('price');

    expect($minmax)->toBeInstanceOf(MinMax::class);
    expect($minmax->min)->toBe(1000);
    expect($minmax->max)->toBe(1500);
    expect($minmax->range())->toBe(500);
});

it('returns average', function () {
    expect($this->product->recordingAverage('price'))->toBe(1233.3333333333333);
});

it('returns trend enum', function () {
    expect($this->product->recordingTrend('price'))->toBe(Trend::Up);
});

it('returns null trend when there is not enough data to determine a direction', function () {
    $solo = Product::create(['name' => 'Solo Widget']);
    $solo->record(data: ['price' => 100]);

    expect($solo->recordingTrend('price'))->toBeNull();
});

it('returns null trend when the metric does not exist', function () {
    $solo = Product::create(['name' => 'Solo Widget']);

    expect($solo->recordingTrend('price'))->toBeNull();
});

it('returns chart data with the configured period', function () {
    $chart = $this->product->recordingChartData('price', period: 'day');

    expect($chart)->toBeInstanceOf(ChartData::class);
    expect($chart->metric)->toBe('price');
    expect($chart->period)->toBe('day');
    expect($chart->count())->toBe(3);
});

it('returns period growth comparing current month vs previous month', function () {
    $product = Product::create(['name' => 'Widget']);

    $product->record(data: ['price' => 800], recordedAt: now()->subMonth()->startOfMonth()->addDay());
    $product->record(data: ['price' => 1000], recordedAt: now()->subMonth()->endOfMonth()->subDay());
    $product->record(data: ['price' => 1500], recordedAt: now()->startOfMonth()->addHour());

    $growth = $product->recordingGrowth('price', period: 'month');

    expect($growth)->toBeInstanceOf(Growth::class);
    expect($growth->start)->toBe(1000);
    expect($growth->end)->toBe(1500);
    expect($growth->difference)->toBe(500);
    expect($growth->trend())->toBe(Trend::Up);
});

it('returns period growth comparing current week vs previous week', function () {
    $product = Product::create(['name' => 'Widget']);

    $product->record(data: ['price' => 500], recordedAt: now()->subWeek()->startOfWeek()->addDay());
    $product->record(data: ['price' => 700], recordedAt: now()->startOfWeek()->addHour());

    $growth = $product->recordingGrowth('price', period: 'week');

    expect($growth->start)->toBe(500);
    expect($growth->end)->toBe(700);
    expect($growth->percentage)->toBe(40.0);
});

it('throws when one of the periods has no recording of the metric', function () {
    $product = Product::create(['name' => 'Widget']);

    $product->record(data: ['price' => 100], recordedAt: now()->startOfMonth()->addHour());

    $product->recordingGrowth('price', period: 'month');
})->throws(InsufficientRecordingsException::class);

it('returns null variant for period growth when periods lack data', function () {
    $product = Product::create(['name' => 'Widget']);

    expect($product->recordingGrowthOrNull('price', period: 'month'))->toBeNull();
});

it('rejects unsupported periods', function () {
    $this->product->recordingGrowth('price', period: 'decade');
})->throws(InvalidArgumentException::class);

it('throws NonNumericMetricException when a recording stores the metric as a string', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['label' => 'on-sale']);

    $product->recordingHistory('label');
})->throws(NonNumericMetricException::class);

it('exposes the metric name and actual type on NonNumericMetricException', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['label' => 'on-sale']);

    try {
        $product->recordingHistory('label');
        $this->fail('Expected NonNumericMetricException.');
    } catch (NonNumericMetricException $e) {
        expect($e->metric)->toBe('label');
        expect($e->actualType)->toBe('string');
        expect($e->recordableClass)->toBe(Product::class);
    }
});

it('silently skips null values when building history', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['price' => 100], recordedAt: now()->subDays(3));
    $product->record(data: ['price' => null], recordedAt: now()->subDays(2));
    $product->record(data: ['price' => 200], recordedAt: now()->subDays(1));

    $history = $product->recordingHistory('price');

    expect($history->count())->toBe(2);
    expect($history->values())->toBe([100, 200]);
});

it('accepts float values for analytics', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['price' => 9.99], recordedAt: now()->subDay());
    $product->record(data: ['price' => 14.49]);

    $growth = $product->recordingGrowth('price');

    expect($growth->start)->toBe(9.99);
    expect($growth->end)->toBe(14.49);
});
