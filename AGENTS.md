# eloquage/beacon

Local semantic search for PHP: store embeddings, query top-k, and filter by
metadata.

- Composer: `eloquage/beacon`
- Entrypoint: `Eloquage\Beacon\Beacon`
- This package is framework-agnostic. The Laravel app at the monorepo root is a
  local test bench only.

## Layout

- `src/Beacon.php` — package identity and the only collection factory
- `src/Collection.php` — public collection lifecycle, validation, filtering,
  and exact scan
- `native/` — optional TypePHP AOT sources (currently empty)
- `tests/` — package-only Pest 5 tests
- `TYPEPHP.md` — extension build contract and current native status
- `project.yml.example` — TypePHP project config (copy to gitignored
  `project.yml`)

## Commands

Run from `packages/beacon`:

```bash
composer test
composer format
vendor/bin/pest --coverage --min=90
```

Tests must exercise the public `Beacon::collection()` API only. Do not
instantiate or call storage, filtering, or scan internals directly. The
package suite runs with Composer autoloading and no Laravel bootstrap or
TypePHP extension; the 90 percent coverage floor applies to `src/`.

## Conventions

- No Illuminate, Laravel service providers, database, HTTP, or other Eloquage
  runtime dependency.
- Always ship a complete pure-PHP fallback. Never require `swoole/typephp`.
- Keep collection instances isolated and use the exact `O(n·d)` scan contract.
- Consumers: PHP 8.3+. Package CI: PHP 8.4. TypePHP compile: PHP 8.5 syntax.

## TypePHP

Extension mode only (`mode: ext`). The pure-PHP implementation is
authoritative and remains usable without a native extension. Build any
optional native experiment in Docker, not on the host:

```bash
# from the laravel-x harness (default builder image)
docker/typephp/build-package.sh beacon

# equivalent package-local command
docker run --rm -v "$PWD":/src -w /src \
  "${ELOQUAGE_TYPEPHP_IMAGE:-ghcr.io/eloquage/typephp-builder:latest}" \
  sh -c 'test -f project.yml || cp project.yml.example project.yml; tpc.php project.yml'
```

Do not add `swoole/typephp` to Composer, add a `main()` function, or claim a
native artifact until the Docker build, extension load, and equivalent public
tests have all succeeded. See `TYPEPHP.md` for the full contract.

## Harness demo

Public behavior must be exercisable from the laravel-x welcome page (`/` →
`resources/views/welcome.blade.php`) with a Feature test. The demo must create,
populate, and query a collection through `Beacon::collection()` rather than
using package internals.

## Humans vs agents

- README — installation, public API, lifecycle, and exact-scan explanation
- This file — agent context, package commands, and implementation boundaries
- TYPEPHP.md — AOT / Docker / release guidance
