<?php

declare(strict_types=1);

namespace Podquilt\Runtime;

use DateTimeImmutable;

/**
 * Supplies the current UTC timestamp to business logic that needs deterministic time handling.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
