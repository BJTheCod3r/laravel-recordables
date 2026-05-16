<?php

declare(strict_types=1);

use BJTheCod3r\Recordables\Support\Percentage;

it('computes a positive change', function () {
    expect(Percentage::change(100, 150))->toBe(50.0);
});

it('computes a negative change', function () {
    expect(Percentage::change(200, 150))->toBe(-25.0);
});

it('returns 0.0 when both sides are zero', function () {
    expect(Percentage::change(0, 0))->toBe(0.0);
});

it('returns 100.0 when growing from zero', function () {
    expect(Percentage::change(0, 50))->toBe(100.0);
});

it('rounds to two decimal places', function () {
    expect(Percentage::change(3, 4))->toBe(33.33);
});

it('accepts float inputs', function () {
    expect(Percentage::change(9.99, 14.49))->toBe(45.05);
});
