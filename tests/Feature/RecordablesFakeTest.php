<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Facades\Recordables;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;

beforeEach(function () {
    config()->set('recordables.auto_record.enabled', false);
});

it('captures recordings without persisting them', function () {
    Recordables::fake();

    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $product->record(data: ['price' => 200]);

    expect($product->refresh()->recordings)->toHaveCount(0);
    Recordables::assertRecorded($product);
});

it('asserts a specific number of recordings', function () {
    Recordables::fake();

    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $product->record(data: ['price' => 200]);
    $product->record(data: ['price' => 300]);

    Recordables::assertRecordedTimes($product, 2);
});

it('asserts recordings match a callback', function () {
    Recordables::fake();

    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $product->record(data: ['price' => 999]);

    Recordables::assertRecordedWith($product, fn (array $data) => $data['price'] === 999);
});

it('asserts nothing recorded', function () {
    Recordables::fake();

    Product::create(['name' => 'Widget']);

    Recordables::assertNothingRecorded();
});

it('asserts not recorded for a different profile', function () {
    Recordables::fake();

    $a = Product::create(['name' => 'Widget A']);
    $b = Product::create(['name' => 'Widget B']);

    $a->record(data: ['price' => 1]);

    Recordables::assertRecorded($a);
    Recordables::assertNotRecorded($b);
});
