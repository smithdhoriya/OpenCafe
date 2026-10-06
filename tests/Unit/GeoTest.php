<?php

use App\Support\Geo;

test('distance between identical coordinates is zero', function () {
    expect(Geo::distanceKm(12.9716, 77.5946, 12.9716, 77.5946))->toBe(0.0);
});

test('distance between two points one degree of latitude apart matches the known great-circle distance', function () {
    // One degree along a meridian is an exact great-circle arc: R * deg2rad(1).
    $expected = 6371.0 * deg2rad(1);

    $distance = Geo::distanceKm(0.0, 0.0, 1.0, 0.0);

    expect($distance)->toBeGreaterThan($expected - 0.01)
        ->toBeLessThan($expected + 0.01);
});

test('distance calculation is deterministic across repeated calls', function () {
    $first = Geo::distanceKm(12.9716, 77.5946, 19.0760, 72.8777);
    $second = Geo::distanceKm(12.9716, 77.5946, 19.0760, 72.8777);

    expect($first)->toBe($second);
});
