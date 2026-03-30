<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Holds the fully normalized application configuration used for a single request.
 */
final readonly class AppConfig
{
    /**
     * @param list<FeedSourceConfig> $feeds
     * @param list<FileSourceConfig> $files
     */
    public function __construct(
        public ChannelConfig $channel,
        public array $feeds,
        public array $files,
        public LogConfig $logs,
    ) {
    }
}
