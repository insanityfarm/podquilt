<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Groups HTTP-related tuning knobs so network behavior can evolve without bloating AppConfig.
 */
final readonly class HttpConfig
{
    public const DEFAULT_MAX_CONCURRENT_REQUESTS = 6;

    public function __construct(
        public int $maxConcurrentRequests,
    ) {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULT_MAX_CONCURRENT_REQUESTS);
    }
}
