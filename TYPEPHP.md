# Ship `eloquage/beacon` as PHP; compile a `.so` only when verified

Consumers install this package with Composer and get a working PHP API under
`src/`. A local Docker build of the current PHP sources and the full public
Pest suite with the loaded extension have been verified for this change. The
generated `.so` remains maintainer/CI output, is not a committed release
artifact, and the pure-PHP collection remains the release evidence. If Composer required
`swoole/typephp`, PHP-only installs would fail for consumers.

## What ships

| Path | Role |
| --- | --- |
| `src/` | Public PHP API and authoritative pure-PHP implementation |
| `native/` | Optional TypePHP sources (currently empty) |
| `project.yml.example` | Ext-mode compile config (copy to gitignored `project.yml`) |

Compile as a PHP **extension** (`mode: ext`). No `main()`. Pure PHP always
ships. A `.so` / `.dll` is maintainer/CI output only and must not be claimed
without a successful build, load check, and equivalent public tests.

Push/PR Pest is the pure-PHP gate (`vendor/bin/pest --coverage --min=90`, no
extension). The local optional native evaluation compiled
`eloquage_beacon.so`, loaded `typephp_eloquage_beacon` inside the builder
container, and ran the same public Pest assertions successfully. This is not a
claim that a consumer release asset or PECL package exists.

`php-version: "8.5"` in YAML is the **syntax** TypePHP accepts, not the
consumer runtime. CLI flags override YAML: [COMPILER_CLI.md](https://github.com/swoole/typephp/blob/master/docs/en/COMPILER_CLI.md).
Limits: [INCOMPATIBLE_PHP_FEATURES.md](https://github.com/swoole/typephp/blob/master/docs/en/INCOMPATIBLE_PHP_FEATURES.md).

## Compile in Docker

The shared builder is Linux-only. Image sources: [`eloquage/typephp-builder`](https://github.com/eloquage/typephp-builder).
Artifacts are gitignored (`*.so`, `build/`). Do not commit `project.yml`.

```bash
docker pull ghcr.io/eloquage/typephp-builder:latest
```

From the laravel-x harness:

```bash
docker/typephp/build-package.sh beacon
```

From this package directory:

```bash
docker run --rm -v "$PWD":/src -w /src \
  "${ELOQUAGE_TYPEPHP_IMAGE:-ghcr.io/eloquage/typephp-builder:latest}" \
  sh -c 'test -f project.yml || cp project.yml.example project.yml; tpc.php project.yml'
```

To build the image yourself instead of pulling:

```bash
docker build -t eloquage-typephp-builder -f packages/typephp-builder/Dockerfile packages/typephp-builder
ELOQUAGE_TYPEPHP_IMAGE=eloquage-typephp-builder docker/typephp/build-package.sh beacon
```

`native/` may be empty. Do not treat PHP source as a proven TypePHP compile
until `tpc` succeeds in the shared image.

## Optional native for consumers

There is no verified consumer extension or release asset from this change.
Only a later release with a verified artifact may document setup-php,
docker-php-ext-install, PECL, or Windows DLL installation instructions.

## What TypePHP will reject

Top-level executable statements (only declarations, `use`, `declare`,
constants). `strict_types=0`. Extra arguments on non-variadic functions.
Composer `swoole/typephp` in this package — ext mode does not need `libphp.so`.

Agents: follow the TypePHP skill in laravel-x (`.cursor/skills/typephp/`). Do
not treat GitHub `docs/en/QUICKSTART.md` or `COMPILATION_MODES.md` as current
if they still show `use native_types` or only two build modes.
