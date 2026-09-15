# eloquage/beacon

Small, framework-agnostic semantic search for PHP. Beacon stores embeddings in
an isolated in-memory collection and returns exact cosine top-k results with
optional scalar metadata filters.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eloquage/beacon.svg?style=flat-square)](https://packagist.org/packages/eloquage/beacon)
[![Tests](https://github.com/eloquage/beacon/actions/workflows/run-tests.yml/badge.svg)](https://github.com/eloquage/beacon/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/eloquage/beacon.svg?style=flat-square)](https://packagist.org/packages/eloquage/beacon)

## Installation

Install via Composer. The pure-PHP implementation works without a framework or
native extension:

```bash
composer require eloquage/beacon
```

## How to use

```php
use Eloquage\Beacon\Beacon;

$beacon = new Beacon();
echo $beacon->name(); // beacon

$collection = Beacon::collection(3);
$collection->upsert('billing-faq', [1, 0, 0], [
    'section' => 'billing',
    'kind' => 'faq',
]);
$collection->upsert('shipping-faq', [0, 1, 0], [
    'section' => 'shipping',
    'kind' => 'faq',
]);

$hits = $collection->query([1, 0, 0], 5, ['section' => 'billing']);
// [['id' => 'billing-faq', 'score' => 1.0,
//   'metadata' => ['section' => 'billing', 'kind' => 'faq']]]
```

`Beacon::collection($dimension)` creates a fresh collection with a positive,
fixed dimension. `upsert()` replaces a record when its ID already exists, so
repeated writes do not increase `count()`. `delete($id)` returns `true` when a
record was removed and `false` when the valid ID was absent.

Vectors must be contiguous numeric lists with the collection dimension and a
non-zero norm. Metadata and filters are shallow associative maps whose values
are strings, integers, floats, booleans, or `null`; filters use strict equality
and compose with AND. Invalid input throws `InvalidArgumentException` before a
record is changed. Query hits contain only `id`, `score`, and `metadata`.

## Reference: exact scan and lifecycle

Beacon keeps the collection in process memory. A query validates its input,
filters eligible records, computes exact cosine similarity, sorts by descending
score, resolves equal scores by ascending bytewise ID, and returns at most `k`
hits. The vector work is `O(n·d)` for `n` eligible records of dimension `d`;
smaller `k` does not make lookup sublinear.

The collection has no persistence or process sharing. Data disappears with the
PHP process, and each call to `Beacon::collection()` is isolated. The v1 API
does not accept ANN/HNSW/IVF controls, hosted databases, HTTP configuration,
range or OR/NOT filters, or hybrid BM25 search. Lexical search belongs in
`eloquage/lex`.

## Explanation: why in-memory first

An in-memory exact scan makes the first implementation predictable: callers can
test the complete lifecycle without a service, index build, migration, or
operational state. It is a useful fit for small local collections and keeps the
public contract focused on records, filtering, and ranking. Persistence and an
approximate nearest-neighbor index can be added later behind the same public
query shape only when their lifecycle and performance trade-offs are specified.

The pure-PHP implementation is the required behavior and works without a
framework or native extension. TypePHP acceleration is optional maintainer
work; see [TYPEPHP.md](TYPEPHP.md) for the Docker-only build contract.

## Testing

```bash
composer test
vendor/bin/pest --coverage --min=90
```

Project-specific maintainer guidance lives in [AGENTS.md](AGENTS.md), the
native build contract in [TYPEPHP.md](TYPEPHP.md), the project history in
[CHANGELOG.md](CHANGELOG.md), and the license in [LICENSE.md](LICENSE.md).
