<?php

declare(strict_types=1);

namespace Podquilt\Logging;

/**
 * Buffers log entries in memory so concurrent work can emit messages in a deterministic order later.
 */
final class BufferedLogger implements LoggerInterface
{
    /**
     * @var list<array{level: LogLevel, message: string}>
     */
    private array $entries = [];

    #[\Override]
    public function log(LogLevel $level, string $message): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => $message,
        ];
    }

    public function flushTo(LoggerInterface $logger): void
    {
        foreach ($this->entries as $entry) {
            $logger->log($entry['level'], $entry['message']);
        }
    }
}
