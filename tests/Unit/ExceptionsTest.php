<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Exceptions\InsufficientRecordingsException;
use BJTheCod3r\Recordables\Exceptions\InvalidRecordableMetricException;
use BJTheCod3r\Recordables\Exceptions\MissingRecordableDefinitionException;
use BJTheCod3r\Recordables\Exceptions\NonNumericMetricException;
use BJTheCod3r\Recordables\Exceptions\RecordablesException;

it('all exceptions extend the base RecordablesException', function () {
    expect(MissingRecordableDefinitionException::for('Foo'))->toBeInstanceOf(RecordablesException::class);
    expect(InvalidRecordableMetricException::for('m', 'Foo'))->toBeInstanceOf(RecordablesException::class);
    expect(InsufficientRecordingsException::for('m', 2, 1))->toBeInstanceOf(RecordablesException::class);
    expect(NonNumericMetricException::for('m', 'Foo', 'bar'))->toBeInstanceOf(RecordablesException::class);
});

it('MissingRecordableDefinitionException exposes the class', function () {
    $e = MissingRecordableDefinitionException::for('App\\Foo');

    expect($e->recordableClass)->toBe('App\\Foo');
});

it('InvalidRecordableMetricException exposes the metric and class', function () {
    $e = InvalidRecordableMetricException::for('price', 'App\\Profile');

    expect($e->metric)->toBe('price');
    expect($e->recordableClass)->toBe('App\\Profile');
});

it('InsufficientRecordingsException exposes counts', function () {
    $e = InsufficientRecordingsException::for('price', 2, 1);

    expect($e->required)->toBe(2);
    expect($e->actual)->toBe(1);
});

it('NonNumericMetricException uses get_debug_type for the actual type', function () {
    $string = NonNumericMetricException::for('m', 'Foo', 'hello');
    $bool = NonNumericMetricException::for('m', 'Foo', true);
    $arr = NonNumericMetricException::for('m', 'Foo', []);

    expect($string->actualType)->toBe('string');
    expect($bool->actualType)->toBe('bool');
    expect($arr->actualType)->toBe('array');
});
