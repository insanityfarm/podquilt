<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use DateTimeImmutable;

/**
 * Precomputes the replay schedule so selection semantics stay deterministic and efficient.
 */
final readonly class ReplayPlan
{
    /**
     * @param list<DateTimeImmutable> $scheduledDates
     */
    public function __construct(
        public bool $enabled,
        public array $scheduledDates = [],
        public ?DateTimeImmutable $originalStartDate = null,
    ) {
    }

    public static function disabled(): self
    {
        return new self(false);
    }

    public function remainingAfter(int $index): int
    {
        return count($this->scheduledDates) - $index;
    }
}
