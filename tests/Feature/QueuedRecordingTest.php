<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Jobs\CreateRecordingJob;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('recordables.auto_record.enabled', false);
});

it('recordOnQueue dispatches a CreateRecordingJob', function () {
    Queue::fake();

    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $product->recordOnQueue(data: ['price' => 200], changeType: 'synced');

    Queue::assertPushed(CreateRecordingJob::class, function (CreateRecordingJob $job) use ($product) {
        return $job->recordableClass === Product::class
            && $job->recordableId === $product->id
            && $job->data === ['price' => 200]
            && $job->changeType === 'synced';
    });
});

it('recordOnQueue uses the configured queue connection and queue name', function () {
    Queue::fake();
    config()->set('recordables.queue.connection', 'redis');
    config()->set('recordables.queue.queue', 'recordings');

    $product = Product::create(['name' => 'Widget']);
    $product->recordOnQueue(data: ['price' => 1]);

    Queue::assertPushedOn('recordings', CreateRecordingJob::class);
});

it('the queued job persists a recording when run', function () {
    $product = Product::create(['name' => 'Widget']);

    (new CreateRecordingJob(
        recordableClass: Product::class,
        recordableId: $product->id,
        data: ['price' => 999],
        changeType: 'synced',
    ))->handle();

    expect($product->refresh()->recordings)->toHaveCount(1);
    expect($product->recordings->first()->data)->toBe(['price' => 999]);
    expect($product->recordings->first()->change_type)->toBe('synced');
});
