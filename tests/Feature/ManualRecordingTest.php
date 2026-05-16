<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Events\RecordingCreated;
use BJTheCod3r\Recordables\Events\RecordingCreating;
use BJTheCod3r\Recordables\Tests\Fixtures\Product;
use BJTheCod3r\Recordables\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Disable auto-recording to isolate manual recording behavior.
    config()->set('recordables.auto_record.enabled', false);
});

it('records manually with explicit data', function () {
    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $user = User::create(['name' => 'Admin']);

    $recording = $product->record(
        data: ['price' => 200],
        changeType: 'synced',
        changeSource: 'cms_sync',
        causer: $user,
    );

    expect($recording->data)->toBe(['price' => 200]);
    expect($recording->change_type)->toBe('synced');
    expect($recording->change_source)->toBe('cms_sync');
    expect($recording->causer_id)->toBe($user->id);
});

it('falls back to toRecording() when no data given', function () {
    $product = Product::create([
        'name' => 'Widget',
        'price' => 100,
        'stock' => 200,
        'views' => 50,
    ]);

    $recording = $product->record();

    expect($recording->data)->toBe([
        'price' => 100,
        'stock' => 200,
        'views' => 50,
    ]);
});

it('skips recording when a RecordingCreating listener cancels the event', function () {
    Event::listen(RecordingCreating::class, fn (RecordingCreating $event) => $event->cancel());

    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    $result = $product->record();

    expect($result)->toBeNull();
    expect($product->refresh()->recordings)->toHaveCount(0);
});

it('persists mutations a listener makes to the event', function () {
    $user = User::create(['name' => 'System']);

    Event::listen(RecordingCreating::class, function (RecordingCreating $event) use ($user) {
        $event->data['injected_by_listener'] = true;
        $event->changeType = 'mutated';
        $event->changeSource = 'listener_test';
        $event->causer = $user;
    });

    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    $recording = $product->record(
        data: ['price' => 200],
        changeType: 'original',
    );

    expect($recording->data)->toBe([
        'price' => 200,
        'injected_by_listener' => true,
    ]);
    expect($recording->change_type)->toBe('mutated');
    expect($recording->change_source)->toBe('listener_test');
    expect($recording->causer_id)->toBe($user->id);
    expect($recording->causer_type)->toBe($user->getMorphClass());
});

it('recordSilently does not dispatch events', function () {
    Event::fake([RecordingCreating::class, RecordingCreated::class]);

    $product = Product::create(['name' => 'Widget', 'price' => 100]);

    $product->recordSilently();

    Event::assertNotDispatched(RecordingCreating::class);
    Event::assertNotDispatched(RecordingCreated::class);
    expect($product->refresh()->recordings)->toHaveCount(1);
});

it('recordIfChanged skips when latest matches current snapshot', function () {
    $product = Product::create(['name' => 'Widget', 'price' => 100]);
    $product->record();
    $countBefore = $product->refresh()->recordings->count();

    $result = $product->recordIfChanged();

    expect($result)->toBeNull();
    expect($product->refresh()->recordings)->toHaveCount($countBefore);
});
