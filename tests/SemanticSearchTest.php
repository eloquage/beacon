<?php

use Eloquage\Beacon\Beacon;

it('creates isolated collections while preserving the package identity entrypoint', function () {
    $beacon = new Beacon;
    $first = Beacon::collection(3);
    $second = Beacon::collection(3);

    expect($beacon->name())->toBe('beacon')
        ->and($first->count())->toBe(0)
        ->and($second->count())->toBe(0);

    $first->upsert('faq-1', [1, 0, 0]);

    expect($first->count())->toBe(1)
        ->and($second->count())->toBe(0)
        ->and($second->query([1, 0, 0], 1))->toBe([]);
});

it('rejects invalid collection dimensions', function (int $dimension) {
    expect(fn () => Beacon::collection($dimension))
        ->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('upserts and replaces records without changing the unique count', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('faq-1', [1, 0], ['section' => 'billing', 'version' => 1]);
    $collection->upsert('faq-1', [0, 1], ['section' => 'shipping', 'version' => 2]);

    expect($collection->count())->toBe(1)
        ->and($collection->query([0, 1], 1)[0])
        ->toBe([
            'id' => 'faq-1',
            'score' => 1.0,
            'metadata' => ['section' => 'shipping', 'version' => 2],
        ]);
});

it('rejects malformed records atomically', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('kept', [1, 0], ['state' => 'original']);

    $invalidRecords = [
        ['', [1, 0], []],
        ['kept', [1], []],
        ['kept', [0 => 1, 2 => 0], []],
        ['kept', [1, NAN], []],
        ['kept', [1, 0], ['not-a-map']],
        ['kept', [1, 0], [0 => 'invalid-field', 'valid' => true]],
        ['kept', [1, 0], ['nested' => ['nope']]],
        ['kept', [1, 0], ['non-finite' => INF]],
    ];

    foreach ($invalidRecords as [$id, $vector, $metadata]) {
        expect(fn () => $collection->upsert($id, $vector, $metadata))
            ->toThrow(InvalidArgumentException::class);
    }

    expect($collection->count())->toBe(1)
        ->and($collection->query([1, 0], 1)[0]['metadata'])
        ->toBe(['state' => 'original']);
});

it('rejects non-list vectors and zero vectors', function () {
    $collection = Beacon::collection(2);

    expect(fn () => $collection->upsert('assoc', ['x' => 1, 'y' => 0]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $collection->upsert('zero', [0, 0]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $collection->query([0, 0], 1))
        ->toThrow(InvalidArgumentException::class);
});

it('returns exact cosine hits with strict metadata AND filtering', function () {
    $collection = Beacon::collection(3);
    $collection->upsert('billing-faq', [1, 0, 0], ['tenant' => 'acme', 'kind' => 'faq']);
    $collection->upsert('billing-guide', [0.9, 0.1, 0], ['tenant' => 'acme', 'kind' => 'guide']);
    $collection->upsert('shipping-faq', [0, 1, 0], ['tenant' => 'acme', 'kind' => 'faq']);
    $collection->upsert('other-tenant', [1, 0, 0], ['tenant' => 'other', 'kind' => 'faq']);

    $hits = $collection->query([1, 0, 0], 10, ['tenant' => 'acme', 'kind' => 'faq']);

    expect($hits)->toHaveCount(2)
        ->and($hits[0]['id'])->toBe('billing-faq')
        ->and($hits[0]['score'])->toBe(1.0)
        ->and($hits[1]['id'])->toBe('shipping-faq')
        ->and($hits[1]['score'])->toBe(0.0)
        ->and(array_keys($hits[0]))->toBe(['id', 'score', 'metadata']);
});

it('supports empty filters, strict scalar equality, and eligible-set truncation', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('integer', [1, 0], ['value' => 1]);
    $collection->upsert('string', [1, 0], ['value' => '1']);
    $collection->upsert('float', [0, 1], ['value' => 1.0]);

    expect($collection->query([1, 0], 10))->toHaveCount(3)
        ->and($collection->query([1, 0], 10, null))->toHaveCount(3)
        ->and($collection->query([1, 0], 10, []))->toHaveCount(3)
        ->and(array_column($collection->query([1, 0], 10, ['value' => 1]), 'id'))
        ->toBe(['integer'])
        ->and($collection->query([1, 0], 2, ['value' => 'missing']))
        ->toBe([]);
});

it('orders equal scores by ascending bytewise ID and snapshots metadata', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('z', [1, 0], ['label' => 'z']);
    $collection->upsert('A', [1, 0], ['label' => 'A']);
    $collection->upsert('a', [1, 0], ['label' => 'a']);

    $hits = $collection->query([1, 0], 2);
    $hits[0]['metadata']['label'] = 'changed';

    expect(array_column($hits, 'id'))->toBe(['A', 'a'])
        ->and($collection->query([1, 0], 1)[0]['metadata'])
        ->toBe(['label' => 'A']);
});

it('validates query inputs before scanning', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('kept', [1, 0], ['state' => 'original']);

    expect(fn () => $collection->query([1, 0], 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $collection->query([1], 1))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $collection->query([1, 0], 1, ['bad' => ['nested']]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $collection->query([1, 0], 1, ['bad' => NAN]))
        ->toThrow(InvalidArgumentException::class)
        ->and($collection->count())->toBe(1);
});

it('returns an empty result for an empty or fully filtered collection', function () {
    $collection = Beacon::collection(2);

    expect($collection->query([1, 0], 1))->toBe([]);

    $collection->upsert('only', [1, 0], ['section' => 'billing']);

    expect($collection->query([1, 0], 1, ['section' => 'shipping']))->toBe([])
        ->and($collection->count())->toBe(1);
});

it('deletes existing and absent IDs idempotently', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('remove-me', [1, 0]);

    expect($collection->delete('remove-me'))->toBeTrue()
        ->and($collection->delete('remove-me'))->toBeFalse()
        ->and($collection->delete('never-there'))->toBeFalse()
        ->and($collection->count())->toBe(0);

    expect(fn () => $collection->delete(''))->toThrow(InvalidArgumentException::class);
});

it('completes the collection lifecycle through the plain PHP API', function () {
    $collection = Beacon::collection(2);
    $collection->upsert('lifecycle', [3, 4], ['active' => true, 'note' => null]);

    expect($collection->count())->toBe(1)
        ->and($collection->query([3, 4], 1)[0]['score'])->toBe(1.0)
        ->and($collection->delete('lifecycle'))->toBeTrue()
        ->and($collection->count())->toBe(0);
});
