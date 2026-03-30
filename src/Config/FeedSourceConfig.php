<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Defines a remote feed source exactly as configured in config.json.
 */
final readonly class FeedSourceConfig
{
    /**
     * @param array<string, string> $filterPatterns
     */
    public function __construct(
        public string $url,
        public ?string $prepend,
        public ?int $itemLimit,
        public ?int $itemMaxAgeDays,
        public array $filterPatterns,
        public ?ReplayConfig $replay,
        public mixed $disabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->disabled !== 'true';
    }

    public function effectiveItemLimit(): int
    {
        return min(SourceDefaults::ITEM_LIMIT, $this->itemLimit ?? SourceDefaults::ITEM_LIMIT);
    }

    public function effectiveItemMaxAgeDays(): int
    {
        return min(SourceDefaults::ITEM_MAX_AGE_DAYS, $this->itemMaxAgeDays ?? SourceDefaults::ITEM_MAX_AGE_DAYS);
    }
}
