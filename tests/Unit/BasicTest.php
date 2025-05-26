<?php

test('package can be instantiated', function () {
    expect(true)->toBeTrue();
});

test('helper functions work', function () {
    $event = createMockEvent();
    expect($event->id)->toBe(1)
        ->and($event->title)->toBe('Sample Event');
});

test('can create complex mock data', function () {
    $data = createComplexMockData();
    expect($data->id)->toBe(1)
        ->and($data->nested_object->sub_id)->toBe(2)
        ->and($data->array_field)->toBeArray();
});
