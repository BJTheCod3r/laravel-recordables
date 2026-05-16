<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Tests\Fixtures\Product;

beforeEach(function () {
    config()->set('recordables.auto_record.enabled', false);

    $this->product = Product::create(['name' => 'Widget']);

    foreach ([1000, 1200, 1500] as $i => $value) {
        $this->product->record(
            data: ['price' => $value],
            recordedAt: now()->subDays(5 - $i),
        );
    }
});

it('returns latest and first recordings', function () {
    expect($this->product->latestRecording()->data['price'])->toBe(1500);
    expect($this->product->firstRecording()->data['price'])->toBe(1000);
});

it('returns the recording at-or-before a point in time', function () {
    $snapshot = $this->product->recordingAt(now()->subDays(4)->addHour());

    expect($snapshot->data['price'])->toBe(1200);
});

it('returns recordings between two dates', function () {
    $recordings = $this->product->recordingsBetween(now()->subDays(4), now()->subDays(2));

    expect($recordings)->toHaveCount(2);
});

it('prunes recordings older than N days', function () {
    $deleted = $this->product->pruneRecordings(keepDays: 4);

    expect($deleted)->toBe(1);
    expect($this->product->refresh()->recordings)->toHaveCount(2);
});

it('prunes recordings keeping only the latest N', function () {
    $deleted = $this->product->pruneRecordings(keepCount: 1);

    expect($deleted)->toBe(2);
    expect($this->product->refresh()->recordings)->toHaveCount(1);
    expect($this->product->recordings->first()->data['price'])->toBe(1500);
});
