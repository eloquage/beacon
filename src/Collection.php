<?php

namespace Eloquage\Beacon;

use InvalidArgumentException;

/**
 * An isolated, in-memory semantic-search collection.
 *
 * Records are normalized at the boundary and queries use an exact linear scan.
 */
final class Collection
{
    /**
     * @var array<string, array{vector: list<float>, metadata: array<string, scalar|null>}>
     */
    private array $records = [];

    public function __construct(private readonly int $dimension)
    {
        if ($dimension <= 0) {
            throw new InvalidArgumentException('Collection dimension must be positive.');
        }
    }

    /**
     * @param  list<int|float>  $vector
     * @param  array<string, scalar|null>  $metadata
     */
    public function upsert(string $id, array $vector, array $metadata = []): void
    {
        self::validateId($id);
        $normalizedVector = $this->normalizeVector($vector, 'Record vector');
        $validatedMetadata = self::validateMetadata($metadata, 'Metadata');

        $this->records[$id] = [
            'vector' => $normalizedVector,
            'metadata' => $validatedMetadata,
        ];
    }

    /**
     * @param  list<int|float>  $vector
     * @param  array<string, scalar|null>|null  $filter
     * @return list<array{id: string, score: float, metadata: array<string, scalar|null>}>
     */
    public function query(array $vector, int $k, ?array $filter = null): array
    {
        if ($k <= 0) {
            throw new InvalidArgumentException('Query k must be positive.');
        }

        $normalizedQuery = $this->normalizeVector($vector, 'Query vector');
        $validatedFilter = self::validateFilter($filter);
        $hits = [];

        foreach ($this->records as $id => $record) {
            if ($validatedFilter !== null && ! self::matchesFilter($record['metadata'], $validatedFilter)) {
                continue;
            }

            $score = 0.0;

            foreach ($normalizedQuery as $index => $component) {
                $score += $component * $record['vector'][$index];
            }

            $hits[] = [
                'id' => $id,
                'score' => max(-1.0, min(1.0, $score)),
                'metadata' => $record['metadata'],
            ];
        }

        usort($hits, static function (array $left, array $right): int {
            $scoreOrder = $right['score'] <=> $left['score'];

            return $scoreOrder !== 0 ? $scoreOrder : strcmp($left['id'], $right['id']);
        });

        return array_slice($hits, 0, $k);
    }

    public function delete(string $id): bool
    {
        self::validateId($id);

        if (! array_key_exists($id, $this->records)) {
            return false;
        }

        unset($this->records[$id]);

        return true;
    }

    public function count(): int
    {
        return count($this->records);
    }

    private static function validateId(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('Record IDs must be non-empty strings.');
        }
    }

    /**
     * @param  array<int|string, mixed>  $vector
     * @return list<float>
     */
    private function normalizeVector(array $vector, string $label): array
    {
        if (count($vector) !== $this->dimension || ! array_is_list($vector)) {
            throw new InvalidArgumentException($label.' must be a contiguous list with '.$this->dimension.' components.');
        }

        $components = [];
        $maximum = 0.0;

        foreach ($vector as $component) {
            if ((! is_int($component) && ! is_float($component)) || ! is_finite((float) $component)) {
                throw new InvalidArgumentException($label.' components must be finite numbers.');
            }

            $component = (float) $component;
            $components[] = $component;
            $maximum = max($maximum, abs($component));
        }

        if ($maximum === 0.0) {
            throw new InvalidArgumentException($label.' must have a non-zero norm.');
        }

        $squaredRatioSum = 0.0;

        foreach ($components as $component) {
            $ratio = $component / $maximum;
            $squaredRatioSum += $ratio * $ratio;
        }

        $scale = sqrt($squaredRatioSum);
        $normalized = [];

        foreach ($components as $component) {
            $normalized[] = ($component / $maximum) / $scale;
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    private static function validateMetadata(array $metadata, string $label): array
    {
        if ($metadata !== [] && array_is_list($metadata)) {
            throw new InvalidArgumentException($label.' must be a shallow associative map.');
        }

        $validated = [];

        foreach ($metadata as $field => $value) {
            if (! is_string($field) || $field === '') {
                throw new InvalidArgumentException($label.' field names must be non-empty strings.');
            }

            if (! is_null($value) && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                throw new InvalidArgumentException($label.' values must be scalar or null.');
            }

            if (is_float($value) && ! is_finite($value)) {
                throw new InvalidArgumentException($label.' float values must be finite.');
            }

            $validated[$field] = $value;
        }

        return $validated;
    }

    /**
     * @param  array<int|string, mixed>|null  $filter
     * @return array<string, scalar|null>|null
     */
    private static function validateFilter(?array $filter): ?array
    {
        if ($filter === null || $filter === []) {
            return $filter;
        }

        return self::validateMetadata($filter, 'Filter');
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     * @param  array<string, scalar|null>  $filter
     */
    private static function matchesFilter(array $metadata, array $filter): bool
    {
        foreach ($filter as $field => $value) {
            if (! array_key_exists($field, $metadata) || $metadata[$field] !== $value) {
                return false;
            }
        }

        return true;
    }
}
