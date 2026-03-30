<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

use Podquilt\Logging\LoggerInterface;
use Podquilt\Logging\LogLevel;

/**
 * Captures log entries in memory so tests can assert warnings and errors without touching disk.
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

    /**
     * @return list<array{level: LogLevel, message: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function messagesForLevel(LogLevel $level): array
    {
        return array_values(array_map(
            static fn (array $entry): string => $entry['message'],
            array_filter(
                $this->entries,
                static fn (array $entry): bool => $entry['level'] === $level,
            ),
        ));
    }
}
