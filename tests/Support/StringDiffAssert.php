<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Reports the first differing byte and a short local snippet when XML snapshots diverge.
 */
final class StringDiffAssert
{
    public static function assertSame(string $expected, string $actual): void
    {
        if ($expected === $actual) {
            Assert::assertSame($expected, $actual);

            return;
        }

        $offset = self::firstDifferenceOffset($expected, $actual);
        $expectedSnippet = self::snippet($expected, $offset);
        $actualSnippet = self::snippet($actual, $offset);

        Assert::fail(sprintf(
            "Strings differ at byte %d.\nExpected: %s\nActual:   %s",
            $offset,
            $expectedSnippet,
            $actualSnippet,
        ));
    }

    private static function firstDifferenceOffset(string $expected, string $actual): int
    {
        $limit = min(strlen($expected), strlen($actual));

        for ($index = 0; $index < $limit; $index++) {
            if ($expected[$index] !== $actual[$index]) {
                return $index;
            }
        }

        return $limit;
    }

    private static function snippet(string $value, int $offset): string
    {
        $start = max(0, $offset - 30);

        return json_encode(substr($value, $start, 60), JSON_THROW_ON_ERROR);
    }
}
