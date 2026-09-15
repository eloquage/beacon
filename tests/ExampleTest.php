<?php

use Eloquage\Beacon\Beacon;

it('bootstraps the package entrypoint', function () {
    $instance = new Beacon;

    expect($instance->name())->toBe('beacon');
});
