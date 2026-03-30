<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Mirrors the nested replay configuration for a remote feed source.
 */
final readonly class ReplayConfig
{
    public function __construct(
        public ?string $schedule,
        public ?string $replayStartDate,
        public ?string $originalStartDate,
    ) {
    }
}
