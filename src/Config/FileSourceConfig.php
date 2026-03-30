<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Defines a synthetic feed item sourced directly from config.json instead of a remote feed.
 */
final readonly class FileSourceConfig
{
    public function __construct(
        public string $url,
        public string $title,
        public string $pubDate,
        public string $description,
        public mixed $disabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->disabled !== 'true';
    }
}
