<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

use DateTimeImmutable;
use Podquilt\Runtime\ClockInterface;

/**
 * Supplies a deterministic timestamp to tests that exercise time-sensitive feed behavior.
 */
final readonly class FrozenClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {
    }

    #[\Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
