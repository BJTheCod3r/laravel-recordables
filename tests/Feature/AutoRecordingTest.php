<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Enums\ChangeType;
use BJTheCod3r\Recordables\Events\RecordingCreating;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;
use Illuminate\Support\Facades\Event;

it('records on create', function () {
    $product = Product::create([
        'name' => 'Widget',
        'price' => 100,
        'stock' => 200,
        'views' => 50,
    ]);

    expect($product->recordings)->toHaveCount(1);
    expect($product->recordings->first()->change_type)->toBe(ChangeType::Created->value);
    expect($product->recordings->first()->data)->toBe([
        'price' => 100,
        'stock' => 200,
        'views' => 50,
    ]);
});

it('records on update when recordable attribute changes', function () {
    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    $product->update(['price' => 150]);

    expect($product->refresh()->recordings)->toHaveCount(2);
    expect($product->recordings->last()->change_type)->toBe(ChangeType::Updated->value);
});

it('skips update recording when recordable attributes are unchanged', function () {
    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    expect($product->refresh()->recordings)->toHaveCount(1);

    $product->update(['non_recordable_column' => 999]);

    expect($product->refresh()->recordings)->toHaveCount(1);
});

it('respects on_create config disabled', function () {
    config()->set('recordables.auto_record.on_create', false);

    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    expect($product->recordings)->toHaveCount(0);
});

it('honors listener cancellation on the auto-record create path', function () {
    Event::listen(RecordingCreating::class, fn (RecordingCreating $event) => $event->cancel());

    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    expect($product->refresh()->recordings)->toHaveCount(0);
});

it('honors listener cancellation on the auto-record update path', function () {
    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    expect($product->refresh()->recordings)->toHaveCount(1);

    Event::listen(RecordingCreating::class, fn (RecordingCreating $event) => $event->cancel());

    $product->update(['price' => 200]);

    expect($product->refresh()->recordings)->toHaveCount(1);
});
