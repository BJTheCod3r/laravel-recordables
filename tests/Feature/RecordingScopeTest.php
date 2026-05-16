<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Models\Recording;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;

beforeEach(function () {
    config()->set('recordables.auto_record.enabled', false);
});

it('whereDataKey filters by a JSON value', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['price' => 100]);
    $product->record(data: ['price' => 500]);
    $product->record(data: ['price' => 1500]);

    $results = Recording::query()->whereDataKey('price', '>', 200)->get();

    expect($results)->toHaveCount(2);
});

it('whereDataKey rejects a key with non-identifier characters', function () {
    Recording::query()->whereDataKey("price'); DROP TABLE recordings; --", '=', 1);
})->throws(InvalidArgumentException::class, 'Invalid JSON key');

it('whereDataKey rejects an operator not in the allowlist', function () {
    Recording::query()->whereDataKey('price', 'OR 1=1 --', 1);
})->throws(InvalidArgumentException::class, 'Invalid operator');

it('whereDataKey accepts standard comparison operators', function () {
    $product = Product::create(['name' => 'Widget']);
    $product->record(data: ['price' => 100]);

    foreach (['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'] as $operator) {
        expect(fn () => Recording::query()->whereDataKey('price', $operator, 1)->get())
            ->not->toThrow(InvalidArgumentException::class);
    }
});