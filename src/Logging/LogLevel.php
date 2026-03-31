<?php

declare(strict_types=1);

namespace Podquilt\Logging;

/**
 * Defines the numeric log levels used throughout Podquilt.
 */
enum LogLevel: int
{
    case Error = 1;
    case Warning = 2;
    case Notice = 3;
    case Info = 4;

    public function label(): string
    {
        return match ($this) {
            self::Error => 'error',
            self::Warning => 'warning',
            self::Notice => 'notice',
            self::Info => 'info',
        };
    }

    public static function fromInt(int $value): self
    {
        return self::tryFrom($value) ?? self::Error;
    }
}
