<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Enums\Trend;
use BJTheCod3r\Recordables\ValueObjects\Delta;
use BJTheCod3r\Recordables\ValueObjects\Growth;
use BJTheCod3r\Recordables\ValueObjects\HistoryPoint;
use Carbon\CarbonImmutable;

it('Growth::between computes difference and percentage', function () {
    $growth = Growth::between(
        'price',
        new HistoryPoint(100, CarbonImmutable::now()->subDay()),
        new HistoryPoint(150, CarbonImmutable::now()),
    );

    expect($growth->difference)->toBe(50);
    expect($growth->percentage)->toBe(50.0);
    expect($growth->trend())->toBe(Trend::Up);
});

it('Growth handles zero start without divide-by-zero', function () {
    $growth = Growth::between(
        'price',
        new HistoryPoint(0, CarbonImmutable::now()->subDay()),
        new HistoryPoint(50, CarbonImmutable::now()),
    );

    expect($growth->percentage)->toBe(100.0);
});

it('Delta::between handles negative growth', function () {
    $delta = Delta::between('price', 200, 150);

    expect($delta->difference)->toBe(-50);
    expect($delta->percentage)->toBe(-25.0);
    expect($delta->isNegative())->toBeTrue();
});

it('Trend::fromDifference returns the right case', function () {
    expect(Trend::fromDifference(10))->toBe(Trend::Up);
    expect(Trend::fromDifference(-10))->toBe(Trend::Down);
    expect(Trend::fromDifference(0))->toBe(Trend::Flat);
});
