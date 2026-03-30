<?php

declare(strict_types=1);

namespace Podquilt\Logging;

/**
 * Minimal structured logging contract for Podquilt's file-backed logging needs.
 */
interface LoggerInterface
{
    public function log(LogLevel $level, string $message): void;
}
