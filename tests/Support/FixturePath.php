<?php

declare(strict_types=1);

namespace Podquilt\Tests\Support;

/**
 * Resolves fixture paths relative to the test suite root.
 */
final class FixturePath
{
    public static function for(string $relativePath): string
    {
        return __DIR__ . '/../Fixtures/' . $relativePath;
    }
}
