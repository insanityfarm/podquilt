<?php

declare(strict_types=1);

namespace Podquilt\Config;

use Podquilt\Logging\LogLevel;

/**
 * Describes how Podquilt should persist operational logs.
 */
final readonly class LogConfig
{
    public function __construct(
        public bool $enabled,
        public LogLevel $level,
        public string $path,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            enabled: true,
            level: LogLevel::Error,
            path: 'logs/podquilt.log',
        );
    }
}
