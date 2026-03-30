<?php

declare(strict_types=1);

namespace Podquilt\Runtime;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads the current wall-clock time in UTC for production execution.
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
