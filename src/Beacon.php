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
    public function name(): string
    {
        return 'beacon';
    }
}
