<?php

namespace Eloquage\Beacon;

/**
 * Primary entrypoint for eloquage/beacon.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Beacon
{
    public static function collection(int $dimension): Collection
    {
        return new Collection($dimension);
    }

    public function name(): string
    {
        return 'beacon';
    }
}
