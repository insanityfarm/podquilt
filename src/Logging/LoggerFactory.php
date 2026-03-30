<?php

declare(strict_types=1);

namespace Podquilt\Logging;

use Podquilt\Config\LogConfig;

/**
 * Keeps logger creation out of application orchestration so tests can substitute custom loggers easily.
 */
final readonly class LoggerFactory
{
    public function create(LogConfig $config, string $projectRoot): LoggerInterface
    {
        return new FileLogger($config, $projectRoot);
    }
}
