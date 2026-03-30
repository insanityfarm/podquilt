<?php

declare(strict_types=1);

namespace Podquilt\Logging;

use Podquilt\Config\LogConfig;

/**
 * Writes Podquilt log entries to disk using the configured threshold and path.
 */
final readonly class FileLogger implements LoggerInterface
{
    public function __construct(
        private LogConfig $config,
        private string $projectRoot,
    ) {
    }

    #[\Override]
    public function log(LogLevel $level, string $message): void
    {
        if (!$this->config->enabled || $this->config->level->value < $level->value) {
            return;
        }

        $path = $this->resolvePath($this->config->path);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $line = sprintf(
            "%s: %s: %s\n",
            date(DATE_ATOM),
            strtoupper($level->label()),
            $message,
        );

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . $path;
    }
}
