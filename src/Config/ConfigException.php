<?php

declare(strict_types=1);

namespace Podquilt\Config;

use RuntimeException;

/**
 * Represents a user-facing configuration problem that should abort request processing.
 */
final class ConfigException extends RuntimeException
{
}
