<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use DateTimeImmutable;
use Podquilt\Runtime\ClockInterface;

/**
 * Encodes Podquilt's inclusive publication-age rule so every source type applies the same window.
 */
final readonly class PublicationWindow
{
    private function __construct(
        private DateTimeImmutable $oldestAllowed,
        private DateTimeImmutable $newestAllowed,
    ) {
    }

    public static function fromClock(ClockInterface $clock, int $maxAgeDays): self
    {
        $newestAllowed = $clock->now();

        return new self(
            oldestAllowed: $newestAllowed->modify(sprintf('-%d days', $maxAgeDays)),
            newestAllowed: $newestAllowed,
        );
    }

    /**
     * Returns true when an item lands inside Podquilt's inclusive age window, including both endpoints.
     */
    public function includes(DateTimeImmutable $publishedAt): bool
    {
        return $publishedAt >= $this->oldestAllowed && $publishedAt <= $this->newestAllowed;
    }
}
